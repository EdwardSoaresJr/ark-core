<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Appointments\ScheduleUrl;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Intake\IntakeEntryQuery;
use App\Ark\Operations\Leads\IngressCreateContactUrl;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use Illuminate\Support\Carbon;

/**
 * Disposable identity + control-center projection for the Communications workspace.
 *
 * Packages contact anchors once for list rows, sticky thread header, and shop context.
 */
final class CommunicationsWorkspaceIdentityProjection
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
        private readonly BalanceDueCalculator $balances,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function forListRow(array $row, ?CustomerCallContext $context = null): array
    {
        $phone = $this->displayPhoneFromRow($row);
        $normalized = PhoneNumber::normalize((string) ($row['normalized_phone'] ?? $row['display_phone'] ?? ''));
        $customer = $context?->customer;
        $known = $customer !== null || filled($row['customer_id'] ?? null);

        $name = match (true) {
            filled($customer?->name) => (string) $customer->name,
            filled($row['headline'] ?? null) && ! $this->isAmbiguousHeadline((string) $row['headline']) => (string) $row['headline'],
            $phone !== null => $phone,
            default => 'Unknown contact',
        };

        if (! $known && $phone !== null) {
            $name = $phone;
        }

        $primaryRo = $context?->openRepairOrders->first();
        $vehicle = $primaryRo?->vehicle;
        $vehicleLabel = $vehicle !== null
            ? trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}")
            : null;
        $vehicleLabel = $vehicleLabel !== '' ? $vehicleLabel : null;
        $roStatus = $primaryRo?->repairOrder->statusDisplayLabel();

        return [
            'name' => $name,
            'phone' => $phone,
            'normalized_phone' => $normalized,
            'email' => filled($customer?->email) ? (string) $customer->email : null,
            'known_customer' => $known,
            'customer_id' => $customer?->id ?? (isset($row['customer_id']) ? (int) $row['customer_id'] : null),
            'vehicle_label' => $vehicleLabel,
            'ro_label' => $primaryRo !== null ? '#'.$primaryRo->repairOrder->repair_order_id : null,
            'ro_status' => $roStatus,
            'link_status' => $known ? 'Customer linked' : 'No customer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forConversation(
        Conversation $conversation,
        ?CustomerCallContext $callContext = null,
        ?Lead $lead = null,
        ?string $turnReason = null,
    ): array {
        $conversation->loadMissing(['owner:id,name']);

        $phoneRaw = $conversation->contact_surface === ConversationContactSurface::Phone
            ? trim((string) $conversation->contact_address)
            : null;
        $callContext ??= ($phoneRaw !== null && $phoneRaw !== ''
            ? $this->callContextResolver->resolve($phoneRaw)
            : null);

        $customer = $callContext?->customer ?? $lead?->customer;
        $primaryRo = $callContext?->openRepairOrders->first()?->repairOrder ?? $lead?->repairOrder;
        if ($primaryRo instanceof RepairOrder) {
            $primaryRo->loadMissing(['vehicle', 'customer']);
            $customer ??= $primaryRo->customer;
        }

        $displayPhone = $phoneRaw !== null && $phoneRaw !== ''
            ? (PhoneNumber::display($phoneRaw) ?? $phoneRaw)
            : (filled($customer?->phone) ? (PhoneNumber::display((string) $customer->phone) ?? (string) $customer->phone) : null);

        $name = match (true) {
            filled($lead?->contact_name) => (string) $lead->contact_name,
            $customer !== null => (string) $customer->name,
            $displayPhone !== null => $displayPhone,
            default => 'Unknown contact',
        };

        $vehicle = $primaryRo?->vehicle;
        $vehicleLabel = $vehicle !== null ? trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}") : null;
        $balance = $primaryRo !== null ? $this->balances->forRepairOrder($primaryRo) : null;
        $lastActivity = ($conversation->posture_changed_at ?? $conversation->updated_at);

        $identity = [
            'name' => $name,
            'phone' => $displayPhone,
            'normalized_phone' => PhoneNumber::normalize($phoneRaw ?? (string) ($customer?->phone ?? '')),
            'email' => filled($customer?->email)
                ? (string) $customer->email
                : (filled($lead?->contact_email) ? (string) $lead->contact_email : null),
            'email_label' => filled($customer?->email)
                ? (string) $customer->email
                : (filled($lead?->contact_email) ? (string) $lead->contact_email : 'No email'),
            'location' => $this->locationLabel($customer),
            'address' => $customer?->display_address,
            'known_customer' => $customer !== null,
            'customer_id' => $customer?->id,
            'vehicle_label' => $vehicleLabel !== '' ? $vehicleLabel : null,
            'vehicle_id' => $vehicle?->id,
            'repair_order_id' => $primaryRo?->id,
            'ro_number' => $primaryRo?->repair_order_id,
            'ro_label' => $primaryRo !== null ? '#'.$primaryRo->repair_order_id : null,
            'ro_status' => $primaryRo?->statusDisplayLabel(),
            'ro_url' => $primaryRo !== null ? route('operations.repair-orders.show', $primaryRo) : null,
            'concern' => filled($primaryRo?->concern_summary)
                ? (string) $primaryRo->concern_summary
                : (filled($lead?->concern) ? (string) $lead->concern : null),
            'turn_label' => $turnReason ?? match ($conversation->waiting_on) {
                ConversationWaitingOn::Shop => 'Waiting on Shop',
                ConversationWaitingOn::Customer => 'Waiting on Customer',
                default => 'Open',
            },
            'status_label' => $conversation->status->label(),
            'assigned' => $conversation->owner?->name,
            'last_activity' => $lastActivity instanceof Carbon
                ? 'Last activity '.$lastActivity->diffForHumans(short: true)
                : null,
            'balance_due_label' => $balance !== null && $balance->balanceDueCents > 0
                ? '$'.number_format($balance->balanceDueCents / 100, 2)
                : null,
            'link_status' => $customer !== null ? 'Customer linked' : 'No customer linked yet',
            'conversation_id' => $conversation->id,
            'origin_label' => $lead !== null ? $lead->source->opportunityLabel() : null,
            'can_mark_handled' => $conversation->status === ConversationStatus::Open,
            'mark_handled_url' => route('operations.communications.conversations.mark-handled', $conversation),
            'mark_read_url' => route('operations.communications.conversations.mark-read', $conversation),
            'actions' => $this->actions(
                phone: $displayPhone,
                normalizedPhone: PhoneNumber::normalize($phoneRaw ?? ''),
                customer: $customer,
                primaryRo: $primaryRo,
                conversation: $conversation,
                callSession: null,
                lead: $lead,
            ),
        ];

        return $identity;
    }

    /**
     * @return array<string, mixed>
     */
    public function forCallSession(CallSession $session, ?CustomerCallContext $callContext = null): array
    {
        $session->loadMissing(['customer', 'owner:id,name', 'repairOrder.vehicle']);
        $phone = $this->callerDisplayPhone->normalizedForSession($session) ?? '';
        $displayPhone = $this->callerDisplayPhone->forSession($session);
        $callContext ??= ($phone !== '' ? $this->callContextResolver->resolve($phone) : null);
        $customer = $callContext?->customer ?? $session->customer;
        $primaryRo = $session->repairOrder ?? $callContext?->openRepairOrders->first()?->repairOrder;
        if ($primaryRo instanceof RepairOrder) {
            $primaryRo->loadMissing('vehicle');
        }

        $vehicle = $primaryRo?->vehicle;
        $vehicleLabel = $vehicle !== null ? trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}") : null;
        $known = $customer !== null;
        $name = $known ? (string) $customer->name : 'Unknown contact';
        $balance = $primaryRo !== null ? $this->balances->forRepairOrder($primaryRo) : null;

        return [
            'name' => $name,
            'phone' => $displayPhone !== '' ? $displayPhone : null,
            'normalized_phone' => $phone !== '' ? $phone : null,
            'email' => filled($customer?->email) ? (string) $customer->email : null,
            'email_label' => filled($customer?->email) ? (string) $customer->email : 'No email',
            'location' => $this->locationLabel($customer),
            'address' => $customer?->display_address,
            'known_customer' => $known,
            'customer_id' => $customer?->id,
            'vehicle_label' => $vehicleLabel !== '' ? $vehicleLabel : null,
            'vehicle_id' => $vehicle?->id,
            'repair_order_id' => $primaryRo?->id,
            'ro_number' => $primaryRo?->repair_order_id,
            'ro_label' => $primaryRo !== null ? '#'.$primaryRo->repair_order_id : null,
            'ro_status' => $primaryRo?->statusDisplayLabel(),
            'ro_url' => $primaryRo !== null ? route('operations.repair-orders.show', $primaryRo) : null,
            'concern' => filled($primaryRo?->concern_summary) ? (string) $primaryRo->concern_summary : null,
            'turn_label' => $session->worked_at !== null ? 'Waiting on Customer' : 'Waiting on Shop',
            'status_label' => $session->status->operationalLabel(),
            'assigned' => $session->owner?->name,
            'last_activity' => $session->started_at !== null
                ? 'Last activity '.$session->started_at->diffForHumans(short: true)
                : null,
            'balance_due_label' => $balance !== null && $balance->balanceDueCents > 0
                ? '$'.number_format($balance->balanceDueCents / 100, 2)
                : null,
            'link_status' => $known ? 'Customer linked' : 'No customer',
            'call_session_id' => $session->id,
            'can_mark_handled' => $session->worked_at === null,
            'mark_handled_url' => route('operations.communications.calls.mark-handled', $session),
            'actions' => $this->actions(
                phone: $displayPhone !== '' ? $displayPhone : null,
                normalizedPhone: $phone !== '' ? $phone : null,
                customer: $customer,
                primaryRo: $primaryRo,
                conversation: null,
                callSession: $session,
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function actions(
        ?string $phone,
        ?string $normalizedPhone,
        ?Customer $customer,
        ?RepairOrder $primaryRo,
        ?Conversation $conversation,
        ?CallSession $callSession,
        ?Lead $lead = null,
    ): array {
        $telHref = $normalizedPhone !== null && $normalizedPhone !== ''
            ? 'tel:'.preg_replace('/\D+/', '', $normalizedPhone)
            : null;
        $mailto = filled($customer?->email)
            ? 'mailto:'.$customer->email
            : (filled($lead?->contact_email) ? 'mailto:'.$lead->contact_email : null);
        $maps = $this->mapsUrl($customer);
        $scheduleUrl = ScheduleUrl::to(array_filter([
            'customer_id' => $customer?->id,
            'vehicle_id' => $primaryRo?->vehicle_id,
            'repair_order_id' => $primaryRo?->id,
            'conversation_id' => $conversation?->id,
        ]));

        $textUrl = $conversation !== null
            ? route('operations.communications.inbox', array_filter([
                'conversation' => $conversation->id,
                'filter' => 'needs',
                'compose' => 'text',
            ]))
            : null;

        $paymentUrl = $primaryRo !== null && $conversation !== null
            ? route('operations.communications.conversations.send-payment', $conversation)
            : null;

        $newRoUrl = match (true) {
            $customer !== null => route('operations.intake.create', ['customer_id' => $customer->id]),
            $lead !== null => route('operations.intake.create', IntakeEntryQuery::fromLead($lead)),
            $normalizedPhone !== null && $normalizedPhone !== '' => route(
                'operations.intake.create',
                IntakeEntryQuery::fromInboundPhoneMessage($normalizedPhone, ''),
            ),
            default => null,
        };

        $createCustomerUrl = match (true) {
            $customer !== null => null,
            $lead !== null => IngressCreateContactUrl::forLead($lead),
            $normalizedPhone !== null && $normalizedPhone !== '' => IngressCreateContactUrl::forPhone(
                $normalizedPhone,
                conversationId: $conversation?->id,
                callSessionId: $callSession?->id,
            ),
            default => null,
        };

        $searchUrl = route('operations.customers.search', ['q' => $normalizedPhone ?? $phone ?? '']);

        return array_values(array_filter([
            ['key' => 'call', 'label' => 'Call', 'type' => 'link', 'url' => $telHref, 'enabled' => $telHref !== null],
            ['key' => 'text', 'label' => 'Text', 'type' => 'link', 'url' => $textUrl, 'enabled' => $textUrl !== null],
            ['key' => 'email', 'label' => 'Email', 'type' => 'link', 'url' => $mailto, 'enabled' => $mailto !== null],
            ['key' => 'schedule', 'label' => 'Schedule', 'type' => 'link', 'url' => $scheduleUrl, 'enabled' => true],
            ['key' => 'payment', 'label' => 'Payment', 'type' => 'link', 'url' => $paymentUrl, 'enabled' => $paymentUrl !== null],
            ['key' => 'directions', 'label' => 'Directions', 'type' => 'link', 'url' => $maps, 'enabled' => $maps !== null, 'target' => '_blank'],
            ['key' => 'new_ro', 'label' => 'New RO', 'type' => 'link', 'url' => $newRoUrl, 'enabled' => $newRoUrl !== null],
            $createCustomerUrl !== null
                ? ['key' => 'create_customer', 'label' => 'Create Customer', 'type' => 'link', 'url' => $createCustomerUrl, 'enabled' => true]
                : null,
            $customer === null
                ? ['key' => 'search_existing', 'label' => 'Search Existing', 'type' => 'link', 'url' => $searchUrl, 'enabled' => true]
                : null,
        ]));
    }

    private function locationLabel(?Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        $parts = array_filter([(string) ($customer->city ?? ''), (string) ($customer->state ?? '')]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function mapsUrl(?Customer $customer): ?string
    {
        $address = $customer?->display_address;

        if (! filled($address)) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $address);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function displayPhoneFromRow(array $row): ?string
    {
        $raw = (string) ($row['display_phone'] ?? $row['normalized_phone'] ?? '');

        if ($raw === '') {
            return null;
        }

        return PhoneNumber::display($raw) ?? $raw;
    }

    private function isAmbiguousHeadline(string $headline): bool
    {
        $normalized = strtolower(trim($headline));

        return in_array($normalized, ['unknown', 'unknown caller', 'unknown contact', 'unknown lead'], true);
    }
}
