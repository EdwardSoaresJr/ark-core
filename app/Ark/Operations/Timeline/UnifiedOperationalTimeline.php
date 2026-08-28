<?php

namespace App\Ark\Operations\Timeline;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Realtime\SessionEvent;
use App\Ark\Operations\Realtime\SessionEventType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use App\Ark\Operations\Timeline\Mappers\ApprovalEventEntryMapper;
use App\Ark\Operations\Timeline\Mappers\CallSessionEventMapper;
use App\Ark\Operations\Timeline\Mappers\CommunicationEventMapper;
use App\Ark\Operations\Timeline\Mappers\ConversationMessageEventMapper;
use App\Ark\Operations\Timeline\Mappers\OperationalEventEntryMapper;
use App\Ark\Operations\Timeline\Mappers\SessionEventTimelineMapper;
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

        if ($this->scopeIsEmpty($scope)) {
            return collect();
        }

        return $this->composeRelationshipTimeline($scope, $limit);
    }

    /**
     * Complete customer conversation timeline — every interaction in one stream.
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
     * RO-scoped customer conversation — same composer as relationship timelines.
     *
     * Returns canonical surface order for event-bubble: oldest → newest.
     *
     * @return Collection<int, OperationalEventEntry>
     */
    public function forRepairOrderRelationship(RepairOrder $repairOrder, int $limit = 50): Collection
    {
        $scope = $this->relationshipTimelineResolver->resolveForRepairOrder($repairOrder, $limit);

        if ($this->scopeIsEmpty($scope)) {
            return collect();
        }

        return $this->composeRelationshipTimeline($scope, $limit)
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
                $entries->push($entry);
            }
        }

        foreach ($this->filterMessagesForTimeline($scope['messages']) as $message) {
            $entries->push($this->messageMapper->map($message));
        }

        foreach ($scope['communication_events'] as $communicationEvent) {
            if (! $communicationEvent->event_type->surfacesOnAdvisorCommsTimeline()) {
                continue;
            }

            $entries->push($this->communicationEventMapper->map($communicationEvent));
        }

        foreach ($scope['operational_events'] as $operationalEvent) {
            $mapped = $this->operationalEventMapper->map($operationalEvent);

            if ($mapped instanceof OperationalEventEntry) {
                $entries->push($mapped);
            }
        }

        foreach ($scope['approval_events'] as $approvalEvent) {
            foreach ($this->approvalEventMapper->map($approvalEvent) as $approvalEntry) {
                $entries->push($approvalEntry);
            }
        }

        return $entries
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
