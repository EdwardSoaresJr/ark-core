<?php

namespace App\Ark\Operations\Attention;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Communications\ConversationWorkboardPresenter;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\LeadConfirmationAuditConversation;
use App\Ark\Operations\Observations\OperationalObservation;
use App\Ark\Operations\Observations\OperationalObservationResolver;
use App\Ark\Operations\Observations\OperationalObservationSeverity;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;

/**
 * Builds explainable attention candidates for conversation entities.
 */
final class ConversationAttentionCandidateBuilder
{
    public function __construct(
        private readonly UnifiedOperationalTimeline $timeline,
        private readonly OperationalObservationResolver $observationResolver,
        private readonly AttentionPressureResolver $pressureResolver,
        private readonly ConversationWorkboardPresenter $conversationPresenter,
        private readonly LeadConfirmationAuditConversation $confirmationAudit,
    ) {}

    public function forConversation(Conversation $conversation): ?AttentionCandidate
    {
        if ($this->confirmationAudit->suppressFromShopTurn($conversation)) {
            return null;
        }

        $conversation->loadMissing(['owner:id,name']);

        $events = $this->timeline->forConversation($conversation)->all();
        $customerId = $this->customerIdForConversation($conversation);

        $observations = $this->observationResolver->resolve($events, [
            'conversation_id' => $conversation->id,
            'customer_id' => $customerId,
        ]);

        if ($conversation->owned_by_user_id === null) {
            $observations[] = new OperationalObservation(
                type: OperationalObservationType::ConversationUnassigned,
                severity: OperationalObservationSeverity::Medium,
                occurredAt: $conversation->posture_changed_at ?? $conversation->updated_at ?? now(),
                customerId: $customerId,
                vehicleId: null,
                repairOrderId: null,
                conversationId: $conversation->id,
                headline: 'Conversation unassigned',
                description: 'No advisor owns this thread.',
                sourceEvents: [],
                metadata: ['authority' => 'conversation'],
            );
        }

        $lane = $conversation->waiting_on === ConversationWaitingOn::Shop ? 'needs_shop' : 'waiting_customer';
        $presented = $this->conversationPresenter->present($conversation, $lane);
        $headline = (string) ($presented['headline'] ?? 'Unknown contact');

        return $this->pressureResolver->candidate(
            entityKey: 'conversation:'.$conversation->id,
            headline: $headline,
            observations: $observations,
            conversationId: $conversation->id,
            customerId: $customerId,
        );
    }

    public function forConversationId(int $conversationId): ?AttentionCandidate
    {
        $conversation = Conversation::query()->find($conversationId);

        return $conversation !== null ? $this->forConversation($conversation) : null;
    }

    private function customerIdForConversation(Conversation $conversation): ?int
    {
        return ConversationLink::query()
            ->where('conversation_id', $conversation->id)
            ->where('linkable_type', Customer::class)
            ->value('linkable_id');
    }
}
