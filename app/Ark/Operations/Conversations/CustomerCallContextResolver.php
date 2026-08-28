<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Orientation\Orientation;
use App\Ark\Orientation\OrientationDensity;

class CustomerCallContextResolver
{
    public function __construct(
        private readonly ConversationTimeline $conversationTimeline,
        private readonly Orientation $orientation,
    ) {}

    public function resolve(?string $phoneNumber, int $messageLimit = 8): ?CustomerCallContext
    {
        $normalized = PhoneNumber::normalize($phoneNumber);

        if ($normalized === null) {
            return null;
        }

        $customer = $this->matchCustomer($normalized);

        if ($customer === null) {
            return new CustomerCallContext(
                normalizedPhone: $normalized,
                displayPhone: PhoneNumber::display($normalized) ?? $normalized,
                customer: null,
                vehicles: collect(),
                openRepairOrders: collect(),
                recentConversationMessages: $this->conversationTimeline->forPhone($normalized, $messageLimit),
            );
        }

        return $this->buildForCustomer($customer, $normalized, $messageLimit);
    }

    /**
     * Attention / Needs You list rows — customer + open RO labels only.
     * Skips orientation + relationship timeline (those belong on selection).
     */
    public function resolveForAttentionList(?string $phoneNumber): ?CustomerCallContext
    {
        $normalized = PhoneNumber::normalize($phoneNumber);

        if ($normalized === null) {
            return null;
        }

        return $this->mapForAttentionList([$normalized])[$normalized] ?? null;
    }

    /**
     * Batch list identity for Communications rows — one customer + open-RO query set.
     *
     * @param  list<string|null>  $phoneNumbers
     * @return array<string, CustomerCallContext> keyed by normalized phone
     */
    public function mapForAttentionList(array $phoneNumbers): array
    {
        $normalizedPhones = collect($phoneNumbers)
            ->map(fn (?string $phone): ?string => PhoneNumber::normalize($phone))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedPhones->isEmpty()) {
            return [];
        }

        $customersByPhone = Customer::query()
            ->whereIn('phone', $normalizedPhones->all())
            ->get()
            ->keyBy(fn (Customer $customer): string => (string) PhoneNumber::normalize((string) $customer->phone));

        $unmatched = $normalizedPhones
            ->reject(fn (string $phone): bool => $customersByPhone->has($phone))
            ->values();

        foreach ($unmatched as $phone) {
            $matched = $this->matchCustomer($phone);

            if ($matched !== null) {
                $customersByPhone[$phone] = $matched;
            }
        }

        $customerIds = $customersByPhone
            ->map(fn (Customer $customer): int => $customer->id)
            ->unique()
            ->values();

        $openRepairOrdersByCustomer = $customerIds->isEmpty()
            ? collect()
            : RepairOrder::query()
                ->with(['vehicle'])
                ->whereIn('customer_id', $customerIds->all())
                ->whereIn('status', RepairOrderStatus::operationalQueueValues())
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy('customer_id');

        $map = [];

        foreach ($normalizedPhones as $phone) {
            $customer = $customersByPhone->get($phone);

            if ($customer === null) {
                $map[$phone] = new CustomerCallContext(
                    normalizedPhone: $phone,
                    displayPhone: PhoneNumber::display($phone) ?? $phone,
                    customer: null,
                    vehicles: collect(),
                    openRepairOrders: collect(),
                    recentConversationMessages: collect(),
                );

                continue;
            }

            $openRepairOrders = ($openRepairOrdersByCustomer->get($customer->id) ?? collect())
                ->map(function (RepairOrder $repairOrder): CustomerCallContextOpenRepairOrder {
                    $vehicle = $repairOrder->vehicle ?? new Vehicle;

                    return new CustomerCallContextOpenRepairOrder(
                        repairOrder: $repairOrder,
                        vehicle: $vehicle,
                        workflowPostureLabel: (string) $repairOrder->statusDisplayLabel(),
                        workflowNextAction: '',
                        orientation: null,
                    );
                })
                ->values();

            $vehicles = $openRepairOrders
                ->map(fn (CustomerCallContextOpenRepairOrder $row): ?Vehicle => $row->vehicle instanceof Vehicle ? $row->vehicle : null)
                ->filter()
                ->unique(fn (Vehicle $vehicle): int => $vehicle->id)
                ->values();

            $map[$phone] = new CustomerCallContext(
                normalizedPhone: $phone,
                displayPhone: PhoneNumber::display($phone) ?? $phone,
                customer: $customer,
                vehicles: $vehicles,
                openRepairOrders: $openRepairOrders,
                recentConversationMessages: collect(),
            );
        }

        return $map;
    }

    public function resolveForCustomer(Customer $customer, int $messageLimit = 8): CustomerCallContext
    {
        $normalized = PhoneNumber::normalize($customer->phone);
        $displayPhone = $normalized !== null
            ? (PhoneNumber::display($normalized) ?? $normalized)
            : ($customer->display_phone ?: 'No phone on file');

        return $this->buildForCustomer(
            $customer,
            $normalized,
            $messageLimit,
            displayPhone: $displayPhone,
        );
    }

    private function buildForCustomer(
        Customer $customer,
        ?string $normalizedPhone,
        int $messageLimit,
        ?string $displayPhone = null,
        bool $includeOrientation = true,
        bool $includeTimeline = true,
    ): CustomerCallContext {
        $resolvedNormalizedPhone = $normalizedPhone ?? PhoneNumber::normalize($customer->phone) ?? '';
        $resolvedDisplayPhone = $displayPhone ?? (PhoneNumber::display($resolvedNormalizedPhone) ?: $resolvedNormalizedPhone);

        $openRepairOrders = $customer->repairOrders()
            ->with($includeOrientation ? ['vehicle', 'communicationEvents'] : ['vehicle'])
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $openRepairOrderContexts = $openRepairOrders
            ->map(function (RepairOrder $repairOrder) use ($includeOrientation): CustomerCallContextOpenRepairOrder {
                $vehicle = $repairOrder->vehicle ?? new Vehicle;

                return new CustomerCallContextOpenRepairOrder(
                    repairOrder: $repairOrder,
                    vehicle: $vehicle,
                    workflowPostureLabel: $includeOrientation
                        ? $repairOrder->communicationPostureLabel()
                        : (string) $repairOrder->statusDisplayLabel(),
                    workflowNextAction: $includeOrientation
                        ? $repairOrder->communicationNextAction()
                        : '',
                    orientation: $includeOrientation
                        ? $this->orientation->repairOrder($repairOrder, OrientationDensity::Standard)
                        : null,
                );
            });

        $vehicles = $openRepairOrders
            ->map(fn (RepairOrder $repairOrder): ?Vehicle => $repairOrder->vehicle)
            ->filter()
            ->unique(fn (Vehicle $vehicle): int => $vehicle->id)
            ->values();

        $recentMessages = collect();

        if ($includeTimeline && $messageLimit > 0) {
            $recentMessages = $this->conversationTimeline->forCustomerRelationship(
                $customer,
                $resolvedNormalizedPhone !== '' ? $resolvedNormalizedPhone : null,
                $messageLimit,
            );
        }

        return new CustomerCallContext(
            normalizedPhone: $resolvedNormalizedPhone,
            displayPhone: $resolvedDisplayPhone,
            customer: $customer,
            vehicles: $vehicles,
            openRepairOrders: $openRepairOrderContexts,
            recentConversationMessages: $recentMessages,
        );
    }

    private function matchCustomer(string $normalizedPhone): ?Customer
    {
        $exact = Customer::query()
            ->where('phone', $normalizedPhone)
            ->first();

        if ($exact) {
            return $exact;
        }

        $needle = strlen($normalizedPhone) > 10
            ? substr($normalizedPhone, -10)
            : $normalizedPhone;

        if (strlen($needle) < 7) {
            return null;
        }

        return Customer::query()
            ->whereNotNull('phone')
            ->where('phone', 'like', '%'.$needle.'%')
            ->orderByDesc('updated_at')
            ->first();
    }
}
