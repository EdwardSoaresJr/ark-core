<?php

namespace App\Ark\Operations\Timeline;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\Realtime\SessionEventType;
use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use App\Ark\Operations\Timeline\Mappers\ApprovalEventEntryMapper;
use App\Ark\Operations\Timeline\Mappers\CallSessionEventMapper;
use App\Ark\Operations\Timeline\Mappers\CommunicationEventMapper;
use App\Ark\Operations\Timeline\Mappers\ConversationMessageEventMapper;
use App\Ark\Operations\Timeline\Mappers\OperationalEventEntryMapper;
use App\Ark\Operations\Timeline\Mappers\SessionEventTimelineMapper;
use App\Ark\Platform\Communications\PlatformSmsTimelineProjection;
use Illuminate\Support\Collection;

/**
 * Composes unified timeline entries from existing authority stores.
 */
final class UnifiedOperationalTimeline
{
    public function __construct(
        private readonly ConversationMessageEventMapper $messageMapper,
        private readonly CallSessionEventMapper $callMapper,
        private readonly CommunicationEventMapper $communicationEventMapper,
        private readonly OperationalEventEntryMapper $operationalEventMapper,
        private readonly SessionEventTimelineMapper $sessionEventMapper,
        private readonly ApprovalEventEntryMapper $approvalEventMapper,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
        private readonly ConversationRelationshipTimelineResolver $relationshipTimelineResolver,
        private readonly PlatformSmsTimelineProjection $platformSms,
    ) {}

    /**
     * @return Collection<int, OperationalEventEntry>
     */
    public function forConversation(Conversation $conversation, int $limit = 50): Collection
    {
        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->with(['participant.user', 'participant.customer'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $messages
            ->map(fn (ConversationMessage $message): OperationalEventEntry => $this->messageMapper->map($message))
            ->values();
    }

    /**
     * @return Collection<int, OperationalEventEntry>
     */
    public function forCallSession(CallSession $session, int $limit = 50): Collection
    {
        $entries = $this->entriesForCallSession($session);

        $phone = $this->callerDisplayPhone->normalizedForSession($session);

        if ($phone === null) {
            return $entries->take($limit)->values();
        }

        $conversation = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->where('contact_address', $phone)
            ->first();

        if ($conversation === null) {
            return $entries->take($limit)->values();
        }

        return $entries
            ->merge($this->forConversation($conversation, $limit))
            ->sortByDesc(fn (OperationalEventEntry $entry): int => $entry->occurredAt->timestamp)
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, ConversationMessage>  $messages
     * @param  iterable<int, CallSession>  $callSessions
     * @return Collection<int, OperationalEventEntry>
     */
    public function forCustomerComms(Collection $messages, iterable $callSessions, int $limit = 48): Collection
    {
        $entries = collect();

        foreach ($callSessions as $callSession) {
            foreach ($this->entriesForCallSession($callSession) as $entry) {
                $entries->push($entry);
            }
        }

        foreach ($this->filterMessagesForTimeline($messages) as $message) {
            $entries->push($this->messageMapper->map($message));
        }

        return $entries
            ->sortByDesc(fn (OperationalEventEntry $entry): int => $entry->occurredAt->timestamp)
            ->take($limit)
            ->values();
    }

    /**
     * Complete customer-relationship timeline (Customer Hub + mobile parity).
     *
     * @return Collection<int, OperationalEventEntry>
     */
    public function forCustomerRelationship(Customer $customer, ?string $normalizedPhone, int $limit = 100): Collection
    {
        $scope = $this->relationshipTimelineResolver->resolveForCustomer($customer, $normalizedPhone, $limit);

        $entries = $this->scopeIsEmpty($scope)
            ? collect()
            : $this->composeRelationshipTimeline($scope, $limit);

        return $this->withPlatformSms($entries, $customer, $normalizedPhone, $limit);
    }

    /**
     * Complete customer conversation timeline - every interaction in one stream.
     *
     * @return Collection<int, OperationalEventEntry>
     */
    public function forConversationRelationship(Conversation $conversation, int $limit = 100): Collection
    {
        $scope = $this->relationshipTimelineResolver->resolve($conversation, $limit);

        if ($this->scopeIsEmpty($scope)) {
            return $this->forConversation($conversation, $limit);
        }

        return $this->composeRelationshipTimeline($scope, $limit);
    }

    /**
     * RO-scoped customer conversation - same composer as relationship timelines.
     *
     * Returns canonical surface order for event-bubble: oldest → newest.
     *
     * @return Collection<int, OperationalEventEntry>
     */
    public function forRepairOrderRelationship(RepairOrder $repairOrder, int $limit = 50): Collection
    {
        $scope = $this->relationshipTimelineResolver->resolveForRepairOrder($repairOrder, $limit);

        $entries = $this->scopeIsEmpty($scope)
            ? collect()
            : $this->composeRelationshipTimeline($scope, $limit);

        $customer = $repairOrder->customer;
        $normalizedPhone = $customer !== null
            ? PhoneNumber::normalize((string) $customer->phone)
            : null;

        if ($customer instanceof Customer) {
            $entries = $this->withPlatformSms($entries, $customer, $normalizedPhone, $limit);
        }

        return $entries
            ->sortBy(fn (OperationalEventEntry $entry): int => $entry->occurredAt->timestamp)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, OperationalEventEntry>
     */
    private function composeRelationshipTimeline(array $scope, int $limit): Collection
    {
        $entries = collect();

        foreach ($scope['call_sessions'] as $callSession) {
            foreach ($this->entriesForCallSession($callSession) as $entry) {
                $entries->push($this->withVisitAttribution($entry, $callSession->repairOrder));
            }
        }

        foreach ($this->filterMessagesForTimeline($scope['messages']) as $message) {
            $entries->push($this->messageMapper->map($message));
        }

        foreach ($scope['communication_events'] as $communicationEvent) {
            if (! $communicationEvent->event_type->surfacesOnAdvisorCommsTimeline()) {
                continue;
            }

            $entries->push($this->withVisitAttribution(
                $this->communicationEventMapper->map($communicationEvent),
                $communicationEvent->repairOrder,
            ));
        }

        foreach ($scope['operational_events'] as $operationalEvent) {
            $mapped = $this->operationalEventMapper->map($operationalEvent);

            if ($mapped instanceof OperationalEventEntry) {
                $entries->push($this->withVisitAttribution(
                    $mapped,
                    $this->repairOrderForOperationalEvent($operationalEvent),
                ));
            }
        }

        foreach ($scope['approval_events'] as $approvalEvent) {
            foreach ($this->approvalEventMapper->map($approvalEvent) as $approvalEntry) {
                $entries->push($this->withVisitAttribution($approvalEntry, $approvalEvent->visit));
            }
        }

        return $entries
            ->sortByDesc(fn (OperationalEventEntry $entry): int => $entry->occurredAt->timestamp)
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, OperationalEventEntry>  $entries
     * @return Collection<int, OperationalEventEntry>
     */
    private function withPlatformSms(Collection $entries, Customer $customer, ?string $normalizedPhone, int $limit): Collection
    {
        $platform = $this->platformSms->forCustomer($customer, $normalizedPhone, $limit);

        if ($platform->isEmpty()) {
            return $entries
                ->sortByDesc(fn (OperationalEventEntry $entry): int => $entry->occurredAt->timestamp)
                ->take($limit)
                ->values();
        }

        return $entries
            ->concat($platform)
            ->sortByDesc(fn (OperationalEventEntry $entry): int => $entry->occurredAt->timestamp)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, OperationalEventEntry>
     */
    private function entriesForCallSession(CallSession $session): Collection
    {
        $session->loadMissing('sessionEvents');

        $entries = collect([$this->callMapper->map($session)]);

        if ($session->sessionEvents->isNotEmpty()) {
            $significant = $session->sessionEvents->filter(
                fn (SessionEvent $event): bool => in_array($event->event_type, [
                    SessionEventType::SessionTransferred,
                    SessionEventType::SessionHeld,
                ], true),
            );

            foreach ($significant as $event) {
                $entries->push($this->sessionEventMapper->map($event));
            }
        }

        return $entries
            ->sortBy(fn (OperationalEventEntry $entry): int => $entry->occurredAt->timestamp)
            ->values();
    }

    private function withVisitAttribution(OperationalEventEntry $entry, mixed $repairOrder): OperationalEventEntry
    {
        if (! $repairOrder instanceof RepairOrder) {
            return $entry;
        }

        $repairOrder->loadMissing('vehicle');
        $vehicle = $repairOrder->vehicle;
        $vehicleLabel = $vehicle !== null
            ? trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}")
            : null;

        return $entry->withMetadata([
            'visit_ro_id' => $repairOrder->id,
            'visit_ro_number' => $repairOrder->repair_order_id,
            'visit_label' => 'RO #'.$repairOrder->repair_order_id,
            'visit_vehicle_label' => $vehicleLabel !== '' ? $vehicleLabel : null,
            'visit_lifecycle_label' => $repairOrder->statusDisplayLabel(),
        ]);
    }

    private function repairOrderForOperationalEvent(OperationalEvent $event): ?RepairOrder
    {
        if ($event->aggregate_type !== RepairOrder::class) {
            return null;
        }

        return RepairOrder::query()->with('vehicle')->find($event->aggregate_id);
    }

    /**
     * @param  Collection<int, ConversationMessage>  $messages
     * @return Collection<int, ConversationMessage>
     */
    private function filterMessagesForTimeline(Collection $messages): Collection
    {
        return $messages->reject(
            fn (ConversationMessage $message): bool => (bool) ($message->metadata['portal_estimate_view'] ?? false),
        )->values();
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function scopeIsEmpty(array $scope): bool
    {
        return $scope['messages']->isEmpty()
            && $scope['call_sessions']->isEmpty()
            && $scope['communication_events']->isEmpty()
            && $scope['operational_events']->isEmpty()
            && $scope['approval_events']->isEmpty();
    }
}
