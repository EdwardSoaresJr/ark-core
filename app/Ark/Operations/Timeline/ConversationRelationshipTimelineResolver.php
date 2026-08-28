<?php

namespace App\Ark\Operations\Timeline;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationParticipant;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationTimeline;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionTimeline;
use App\Ark\Operations\Timeline\Mappers\OperationalEventEntryMapper;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Resolves every authority store that belongs on a customer conversation timeline.
 */
final class ConversationRelationshipTimelineResolver
{
    public function __construct(
        private readonly ConversationTimeline $conversationTimeline,
        private readonly CallSessionTimeline $callSessionTimeline,
    ) {}

    /**
     * @return array{
     *     messages: Collection<int, \App\Ark\Operations\Conversations\ConversationMessage>,
     *     call_sessions: EloquentCollection<int, CallSession>,
     *     communication_events: EloquentCollection<int, CommunicationEvent>,
     *     operational_events: EloquentCollection<int, OperationalEvent>,
     *     approval_events: EloquentCollection<int, ApprovalEvent>,
     * }
     */
    public function resolve(Conversation $conversation, int $limit): array
    {
        $normalizedPhone = $conversation->contact_surface === ConversationContactSurface::Phone
            ? PhoneNumber::normalize((string) $conversation->contact_address)
            : null;

        $customer = $this->customerForConversation($conversation, $normalizedPhone);

        if ($customer instanceof Customer) {
            return $this->resolveForCustomer($customer, $normalizedPhone, $limit);
        }

        if ($normalizedPhone !== null) {
            return [
                'messages' => $this->conversationTimeline->forPhone($normalizedPhone, $limit),
                'call_sessions' => $this->callSessionsForPhone($normalizedPhone, $limit),
                'communication_events' => new EloquentCollection,
                'operational_events' => new EloquentCollection,
                'approval_events' => new EloquentCollection,
            ];
        }

        return [
            'messages' => collect(),
            'call_sessions' => new EloquentCollection,
            'communication_events' => new EloquentCollection,
            'operational_events' => new EloquentCollection,
            'approval_events' => new EloquentCollection,
        ];
    }

    /**
     * Evidence explicitly linked to this repair order only — no customer time-window inference.
     *
     * @return array{
     *     messages: Collection<int, \App\Ark\Operations\Conversations\ConversationMessage>,
     *     call_sessions: EloquentCollection<int, CallSession>,
     *     communication_events: EloquentCollection<int, CommunicationEvent>,
     *     operational_events: EloquentCollection<int, OperationalEvent>,
     *     approval_events: EloquentCollection<int, ApprovalEvent>,
     * }
     */
    public function resolveForRepairOrder(RepairOrder $repairOrder, int $limit): array
    {
        return [
            'messages' => $this->conversationTimeline->forRepairOrder($repairOrder, $limit),
            'call_sessions' => $this->callSessionTimeline->forRepairOrder($repairOrder, $limit),
            'communication_events' => CommunicationEvent::query()
                ->with(['repairOrder.vehicle', 'repairOrder.customer', 'creator'])
                ->where('repair_order_id', $repairOrder->id)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
            'operational_events' => OperationalEvent::query()
                ->with('actor:id,name')
                ->whereIn('event_name', OperationalEventEntryMapper::CUSTOMER_TIMELINE_NAMES)
                ->where('aggregate_type', RepairOrder::class)
                ->where('aggregate_id', $repairOrder->id)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
            'approval_events' => ApprovalEvent::query()
                ->with(['visit.customer', 'visit.vehicle', 'revocation'])
                ->where('visit_id', $repairOrder->id)
                ->orderByDesc('approved_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
        ];
    }

    /**
     * @return array{
     *     messages: Collection<int, \App\Ark\Operations\Conversations\ConversationMessage>,
     *     call_sessions: EloquentCollection<int, CallSession>,
     *     communication_events: EloquentCollection<int, CommunicationEvent>,
     *     operational_events: EloquentCollection<int, OperationalEvent>,
     *     approval_events: EloquentCollection<int, ApprovalEvent>,
     * }
     */
    public function resolveForCustomer(Customer $customer, ?string $normalizedPhone, int $limit): array
    {
        $repairOrderIds = RepairOrder::query()
            ->where('customer_id', $customer->id)
            ->pluck('id');

        $vehicleIds = RepairOrder::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('vehicle_id')
            ->pluck('vehicle_id')
            ->unique()
            ->values();

        return [
            'messages' => $this->conversationTimeline->forCustomerRelationship($customer, $normalizedPhone, $limit),
            'call_sessions' => $this->callSessionTimeline->forCustomer($customer, $limit),
            'communication_events' => CommunicationEvent::query()
                ->with(['repairOrder.vehicle', 'repairOrder.customer', 'creator'])
                ->whereHas('repairOrder', fn ($query) => $query->where('customer_id', $customer->id))
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
            'operational_events' => $this->operationalEventsForCustomer($repairOrderIds, $vehicleIds, $limit),
            'approval_events' => ApprovalEvent::query()
                ->with(['visit.customer', 'visit.vehicle', 'revocation'])
                ->whereHas('visit', fn ($query) => $query->where('customer_id', $customer->id))
                ->orderByDesc('approved_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
        ];
    }

    /**
     * @param  Collection<int, int|string>  $repairOrderIds
     * @param  Collection<int, int|string>  $vehicleIds
     * @return EloquentCollection<int, OperationalEvent>
     */
    private function operationalEventsForCustomer(Collection $repairOrderIds, Collection $vehicleIds, int $limit): EloquentCollection
    {
        if ($repairOrderIds->isEmpty() && $vehicleIds->isEmpty()) {
            return new EloquentCollection;
        }

        return OperationalEvent::query()
            ->with('actor:id,name')
            ->whereIn('event_name', OperationalEventEntryMapper::CUSTOMER_TIMELINE_NAMES)
            ->where(function ($query) use ($repairOrderIds, $vehicleIds): void {
                if ($repairOrderIds->isNotEmpty()) {
                    $query->where(function ($scoped) use ($repairOrderIds): void {
                        $scoped->where('aggregate_type', RepairOrder::class)
                            ->whereIn('aggregate_id', $repairOrderIds);
                    });
                }

                if ($vehicleIds->isNotEmpty()) {
                    $method = $repairOrderIds->isNotEmpty() ? 'orWhere' : 'where';

                    $query->{$method}(function ($scoped) use ($vehicleIds): void {
                        $scoped->where('aggregate_type', Vehicle::class)
                            ->whereIn('aggregate_id', $vehicleIds);
                    });
                }
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    private function customerForConversation(Conversation $conversation, ?string $normalizedPhone): ?Customer
    {
        $participantCustomerId = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('participant_type', ConversationParticipantType::Customer)
            ->whereNotNull('customer_id')
            ->value('customer_id');

        if ($participantCustomerId !== null) {
            return Customer::query()->find($participantCustomerId);
        }

        $linkedCustomerId = ConversationLink::query()
            ->where('conversation_id', $conversation->id)
            ->where('linkable_type', (new Customer)->getMorphClass())
            ->value('linkable_id');

        if ($linkedCustomerId !== null) {
            return Customer::query()->find($linkedCustomerId);
        }

        if ($normalizedPhone === null) {
            return null;
        }

        $exact = Customer::query()->where('phone', $normalizedPhone)->first();

        if ($exact instanceof Customer) {
            return $exact;
        }

        $needle = strlen($normalizedPhone) > 10
            ? substr($normalizedPhone, -10)
            : $normalizedPhone;

        if (strlen($needle) < 7) {
            return null;
        }

        return Customer::query()
            ->where('phone', 'like', '%'.$needle)
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @return EloquentCollection<int, CallSession>
     */
    private function callSessionsForPhone(string $normalizedPhone, int $limit): EloquentCollection
    {
        return CallSession::query()
            ->where('normalized_from', $normalizedPhone)
            ->excludingExtensionLegArtifacts()
            ->with(['owner:id,name', 'repairOrder'])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
