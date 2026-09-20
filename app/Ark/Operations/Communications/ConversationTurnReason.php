<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Leads\ConversationLeadResolver;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Messaging\MessageActionContract;
use App\Ark\Operations\Messaging\MessageActionReply;

/**
 * Turn-based reason labels — explainable from authority, never generative.
 */
final class ConversationTurnReason
{
    public function __construct(
        private readonly ConversationLeadResolver $conversationLeads,
    ) {}

    /**
     * @return array{state: string, state_label: string, turn_label: string}
     */
    public function for(Conversation $conversation, ?Lead $lead = null): array
    {
        if ($conversation->waiting_on === ConversationWaitingOn::Customer) {
            return [
                'state' => 'customer_turn',
                'state_label' => 'Waiting on Customer',
                'turn_label' => 'Waiting on Customer',
            ];
        }

        $lead ??= $this->conversationLeads->forTurn($conversation);
        $hasAdvisorOutbound = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', OperationalCommunicationDirection::Outbound)
            ->whereHas('participant', fn ($query) => $query->where('participant_type', ConversationParticipantType::Advisor->value))
            ->exists();

        if (! $hasAdvisorOutbound || ($lead !== null && $lead->isNotContacted())) {
            return [
                'state' => 'shop_turn',
                'state_label' => 'Needs first response',
                'turn_label' => 'Needs first response',
            ];
        }

        $actionLabel = $this->messageActionAttentionLabel($conversation);

        if ($actionLabel !== null) {
            return [
                'state' => 'shop_turn',
                'state_label' => $actionLabel,
                'turn_label' => $actionLabel,
            ];
        }

        return [
            'state' => 'shop_turn',
            'state_label' => 'Customer replied',
            'turn_label' => 'Customer replied',
        ];
    }

    private function messageActionAttentionLabel(Conversation $conversation): ?string
    {
        $latest = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', OperationalCommunicationDirection::Inbound)
            ->orderByDesc('occurred_at')
            ->first();

        if ($latest === null) {
            return null;
        }

        $reply = is_array($latest->metadata)
            ? ($latest->metadata[MessageActionContract::META_REPLY] ?? null)
            : null;

        return match ($reply) {
            MessageActionReply::Reschedule->value => MessageActionReply::Reschedule->attentionLabel(),
            MessageActionReply::Callback->value => MessageActionReply::Callback->attentionLabel(),
            default => null,
        };
    }
}
