<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Illuminate\Support\Collection;

/**
 * Thread, customer overlay, and current visit for the Communications workspace.
 *
 * Does not invent a visit. Linked RO wins. Otherwise one open RO may be inferred.
 */
final class CommunicationsRelationshipContextResolver
{
    /**
     * @return array<string, mixed>
     */
    public function forConversation(Conversation $conversation, ?Lead $lead = null): array
    {
        $conversation->loadMissing(['owner:id,name']);

        $mapped = $this->mapForConversations(collect([$conversation]));

        $context = $mapped[$conversation->id] ?? $this->unresolvedThread($conversation);

        if ($lead !== null) {
            $context = $this->overlayLead($context, $lead);
        }

        return $context;
    }

    /**
     * @param  Collection<int, Conversation>  $conversations
     * @return array<int, array<string, mixed>>
     */
    public function mapForConversations(Collection $conversations): array
    {
        if ($conversations->isEmpty()) {
            return [];
        }

        $conversationIds = $conversations->pluck('id')->all();
        $links = ConversationLink::query()
            ->whereIn('conversation_id', $conversationIds)
            ->with('linkable')
            ->get()
            ->groupBy('conversation_id');

        $participantCustomerIds = ConversationParticipant::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('participant_type', ConversationParticipantType::Customer)
            ->whereNotNull('customer_id')
            ->pluck('customer_id', 'conversation_id');

        $phones = $conversations
            ->filter(fn (Conversation $conversation): bool => $conversation->contact_surface === ConversationContactSurface::Phone)
            ->map(fn (Conversation $conversation): ?string => PhoneNumber::normalize((string) $conversation->contact_address))
            ->filter()
            ->unique()
            ->values();

        $customersByPhone = $phones->isEmpty()
            ? collect()
            : Customer::query()
                ->whereIn('phone', $phones->all())
                ->get()
                ->keyBy(fn (Customer $customer): string => (string) PhoneNumber::normalize((string) ($customer->getAttributes()['phone'] ?? '')));

        $customerIds = collect($participantCustomerIds->all())
            ->merge($links->flatten()->map(fn (ConversationLink $link): mixed => $link->linkable instanceof Customer ? $link->linkable->id : null))
            ->merge($customersByPhone->map(fn (Customer $customer): int => $customer->id))
            ->filter()
            ->unique()
            ->values();

        $customersById = $customerIds->isEmpty()
            ? collect()
            : Customer::query()->whereIn('id', $customerIds->all())->get()->keyBy('id');

        $openByCustomer = $customerIds->isEmpty()
            ? collect()
            : RepairOrder::query()
                ->with('vehicle')
                ->whereIn('customer_id', $customerIds->all())
                ->whereIn('status', RepairOrderStatus::operationalQueueValues())
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy('customer_id');

        $map = [];

        foreach ($conversations as $conversation) {
            $phone = $conversation->contact_surface === ConversationContactSurface::Phone
                ? PhoneNumber::normalize((string) $conversation->contact_address)
                : null;
            $displayPhone = $phone !== null
                ? (PhoneNumber::display($phone) ?? $phone)
                : (trim((string) $conversation->contact_address) !== ''
                    ? trim((string) $conversation->contact_address)
                    : null);

            $customer = null;
            $participantCustomerId = $participantCustomerIds->get($conversation->id);
            if ($participantCustomerId !== null) {
                $customer = $customersById->get((int) $participantCustomerId);
            }

            $conversationLinks = $links->get($conversation->id) ?? collect();
            if ($customer === null) {
                foreach ($conversationLinks as $link) {
                    if ($link->linkable instanceof Customer) {
                        $customer = $link->linkable;
                        break;
                    }
                }
            }

            if ($customer === null && $phone !== null) {
                $customer = $customersByPhone->get($phone);
            }

            $linkedRepairOrders = $conversationLinks
                ->map(fn (ConversationLink $link): mixed => $link->linkable)
                ->filter(fn (mixed $linkable): bool => $linkable instanceof RepairOrder)
                ->unique(fn (RepairOrder $repairOrder): int => $repairOrder->id)
                ->values();

            foreach ($linkedRepairOrders as $linked) {
                $linked->loadMissing('vehicle');
            }

            $openRepairOrders = $customer !== null
                ? ($openByCustomer->get($customer->id) ?? collect())->values()
                : collect();

            $visit = $this->resolveVisit($linkedRepairOrders, $openRepairOrders);
            $waitingOn = $conversation->waiting_on ?? ConversationWaitingOn::Shop;

            $map[$conversation->id] = [
                'thread' => [
                    'conversation_id' => $conversation->id,
                    'surface' => $conversation->contact_surface->value,
                    'address' => (string) $conversation->contact_address,
                    'phone' => $displayPhone,
                    'normalized_phone' => $phone,
                ],
                'customer' => $customer instanceof Customer
                    ? [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => filled($customer->email) ? (string) $customer->email : null,
                        'matched' => true,
                        'status' => 'Customer',
                    ]
                    : [
                        'id' => null,
                        'name' => null,
                        'email' => null,
                        'matched' => false,
                        'status' => 'Unmatched',
                    ],
                'current_visit' => $visit,
                'open_repair_orders' => $openRepairOrders,
                'turn' => [
                    'waiting_on' => $waitingOn->value,
                    'label' => $waitingOn->label(),
                    'is_shop_turn' => $waitingOn === ConversationWaitingOn::Shop,
                ],
                'assigned' => $conversation->owner?->name,
            ];
        }

        return $map;
    }

    /**
     * @param  Collection<int, RepairOrder>  $linkedRepairOrders
     * @param  Collection<int, RepairOrder>  $openRepairOrders
     * @return array<string, mixed>
     */
    private function resolveVisit(Collection $linkedRepairOrders, Collection $openRepairOrders): array
    {
        $openStatus = RepairOrderStatus::operationalQueueValues();
        $openLinked = $linkedRepairOrders
            ->filter(fn (RepairOrder $repairOrder): bool => in_array($repairOrder->status->value, $openStatus, true))
            ->values();

        if ($openLinked->count() === 1) {
            return $this->visitPayload($openLinked->first(), CommunicationsVisitSource::Linked, $openRepairOrders);
        }

        if ($openLinked->count() > 1) {
            return $this->multiplePayload($openLinked, CommunicationsVisitSource::Linked);
        }

        if ($linkedRepairOrders->isNotEmpty()) {
            $linked = $linkedRepairOrders->sortByDesc(fn (RepairOrder $repairOrder): string => (string) $repairOrder->updated_at)->first();
            $payload = $this->emptyPayload(
                CommunicationsVisitSource::None,
                $openRepairOrders,
                'Linked visit is not open',
            );
            $payload['linked_visit'] = $linked instanceof RepairOrder ? $this->repairOrderSummary($linked) : null;

            return $payload;
        }

        if ($openRepairOrders->count() === 1) {
            return $this->visitPayload($openRepairOrders->first(), CommunicationsVisitSource::Inferred, $openRepairOrders);
        }

        if ($openRepairOrders->count() > 1) {
            return $this->multiplePayload($openRepairOrders, CommunicationsVisitSource::Multiple);
        }

        return $this->emptyPayload(CommunicationsVisitSource::None, $openRepairOrders);
    }

    /**
     * @param  Collection<int, RepairOrder>  $openRepairOrders
     * @return array<string, mixed>
     */
    private function visitPayload(RepairOrder $repairOrder, CommunicationsVisitSource $source, Collection $openRepairOrders): array
    {
        $summary = $this->repairOrderSummary($repairOrder);

        return [
            'source' => $source->value,
            'source_label' => $source->label(),
            'repair_order' => $repairOrder,
            'repair_order_id' => $repairOrder->id,
            'ro_number' => $repairOrder->repair_order_id,
            'ro_label' => $summary['ro_label'],
            'vehicle_label' => $summary['vehicle_label'],
            'lifecycle_label' => $summary['lifecycle_label'],
            'url' => route('operations.repair-orders.show', $repairOrder),
            'open_visits' => $openRepairOrders->map(fn (RepairOrder $row): array => $this->repairOrderSummary($row))->values()->all(),
            'label' => $source === CommunicationsVisitSource::Inferred
                ? 'Inferred current visit'
                : 'Current visit',
        ];
    }

    /**
     * @param  Collection<int, RepairOrder>  $openRepairOrders
     * @return array<string, mixed>
     */
    private function multiplePayload(Collection $openRepairOrders, CommunicationsVisitSource $source): array
    {
        return [
            'source' => CommunicationsVisitSource::Multiple->value,
            'source_label' => $source === CommunicationsVisitSource::Linked
                ? 'Multiple linked visits'
                : CommunicationsVisitSource::Multiple->label(),
            'repair_order' => null,
            'repair_order_id' => null,
            'ro_number' => null,
            'ro_label' => null,
            'vehicle_label' => null,
            'lifecycle_label' => null,
            'url' => null,
            'open_visits' => $openRepairOrders->map(fn (RepairOrder $row): array => $this->repairOrderSummary($row))->values()->all(),
            'label' => 'Multiple open visits',
        ];
    }

    /**
     * @param  Collection<int, RepairOrder>  $openRepairOrders
     * @return array<string, mixed>
     */
    private function emptyPayload(CommunicationsVisitSource $source, Collection $openRepairOrders, ?string $note = null): array
    {
        return [
            'source' => CommunicationsVisitSource::None->value,
            'source_label' => $note ?? $source->label(),
            'repair_order' => null,
            'repair_order_id' => null,
            'ro_number' => null,
            'ro_label' => null,
            'vehicle_label' => null,
            'lifecycle_label' => null,
            'url' => null,
            'open_visits' => $openRepairOrders->map(fn (RepairOrder $row): array => $this->repairOrderSummary($row))->values()->all(),
            'label' => 'No current visit',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function repairOrderSummary(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing('vehicle');
        $vehicle = $repairOrder->vehicle;
        $vehicleLabel = $vehicle !== null
            ? trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}")
            : null;

        return [
            'repair_order_id' => $repairOrder->id,
            'ro_number' => $repairOrder->repair_order_id,
            'ro_label' => '#'.$repairOrder->repair_order_id,
            'vehicle_label' => $vehicleLabel !== '' ? $vehicleLabel : null,
            'lifecycle_label' => $repairOrder->statusDisplayLabel(),
            'url' => route('operations.repair-orders.show', $repairOrder),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function overlayLead(array $context, Lead $lead): array
    {
        $lead->loadMissing(['customer', 'repairOrder.vehicle']);

        if (! ($context['customer']['matched'] ?? false)) {
            $context['customer']['name'] = filled($lead->contact_name) ? (string) $lead->contact_name : $context['customer']['name'];
            $context['customer']['email'] = filled($lead->contact_email)
                ? (string) $lead->contact_email
                : $context['customer']['email'];
            $context['customer']['status'] = filled($lead->customer_id) ? 'Customer' : 'Lead';
            $context['customer']['matched'] = filled($lead->customer_id);
            $context['customer']['id'] = $lead->customer_id ?? $context['customer']['id'];
        }

        $context['lead_id'] = $lead->id;
        $context['origin_label'] = $lead->source->opportunityLabel();

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function unresolvedThread(Conversation $conversation): array
    {
        $phone = $conversation->contact_surface === ConversationContactSurface::Phone
            ? PhoneNumber::normalize((string) $conversation->contact_address)
            : null;

        return [
            'thread' => [
                'conversation_id' => $conversation->id,
                'surface' => $conversation->contact_surface->value,
                'address' => (string) $conversation->contact_address,
                'phone' => $phone !== null ? (PhoneNumber::display($phone) ?? $phone) : null,
                'normalized_phone' => $phone,
            ],
            'customer' => [
                'id' => null,
                'name' => null,
                'email' => null,
                'matched' => false,
                'status' => 'Unmatched',
            ],
            'current_visit' => $this->emptyPayload(CommunicationsVisitSource::None, collect()),
            'open_repair_orders' => collect(),
            'turn' => [
                'waiting_on' => ($conversation->waiting_on ?? ConversationWaitingOn::Shop)->value,
                'label' => ($conversation->waiting_on ?? ConversationWaitingOn::Shop)->label(),
                'is_shop_turn' => ($conversation->waiting_on ?? ConversationWaitingOn::Shop) === ConversationWaitingOn::Shop,
            ],
            'assigned' => $conversation->owner?->name,
        ];
    }
}
