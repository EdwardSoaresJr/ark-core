<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Leads\ConversationLeadResolver;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Leads\LeadConfirmationAuditConversation;
use App\Ark\Orientation\Orientation;
use App\Ark\Runtime\Database\SchemaPresence;
use App\Models\User;

/**
 * Conversations where the shop owes the next move - turn-based Needs Attention.
 *
 * No age window: unreplied customer SMS/MMS stays until the shop replies or
 * resolves. Live unread + call interrupt still use CommunicationsQueueWindow.
 */
final class ShopTurnAttentionQueue
{
    public function __construct(
        private readonly ShopTurnAttentionPresenter $presenter,
        private readonly LeadConfirmationAuditConversation $confirmationAudit,
        private readonly ConversationLeadResolver $conversationLeads,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly ConversationTurnReason $turnReason,
    ) {}

    /**
     * @param  list<int>  $excludeConversationIds  Already represented (e.g. unread SMS rows).
     * @return list<array<string, mixed>>
     */
    public function rowsFor(?User $viewer, array $excludeConversationIds = []): array
    {
        if ($viewer === null || ! SchemaPresence::hasColumn('conversations', 'waiting_on')) {
            return [];
        }

        $conversations = Conversation::query()
            ->openPosture()
            ->where('waiting_on', ConversationWaitingOn::Shop->value)
            ->whereNotNull('posture_changed_at')
            ->with([
                'messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1),
                'messages.attachments',
                'messages.participant.customer',
                'owner:id,name',
            ])
            ->orderBy('posture_changed_at')
            ->limit(100)
            ->get();

        if ($excludeConversationIds !== []) {
            $conversations = $conversations->reject(
                fn (Conversation $conversation): bool => in_array($conversation->id, $excludeConversationIds, true),
            );
        }

        if ($conversations->isEmpty()) {
            return [];
        }

        $leadsByConversationId = $this->conversationLeads->mapForConversations($conversations);

        $conversations = $conversations->reject(
            fn (Conversation $conversation): bool => $this->confirmationAudit->suppressFromShopTurn(
                $conversation,
                $leadsByConversationId[$conversation->id] ?? null,
            ),
        );

        if ($conversations->isEmpty()) {
            return [];
        }

        $contexts = $this->callContextResolver->mapForAttentionList(
            $conversations->map(fn (Conversation $conversation): string => (string) $conversation->contact_address)->all(),
        );
        Orientation::preloadForContexts($contexts);
        $advisorOutbound = $this->turnReason->advisorOutboundConversationIds($conversations);

        return $conversations
            ->map(function (Conversation $conversation) use ($leadsByConversationId, $contexts, $advisorOutbound): array {
                $phone = PhoneNumber::normalize((string) $conversation->contact_address);

                return $this->presenter->present(
                    $conversation,
                    $leadsByConversationId[$conversation->id] ?? null,
                    $phone !== null ? ($contexts[$phone] ?? null) : null,
                    isset($advisorOutbound[$conversation->id]),
                );
            })
            ->values()
            ->all();
    }
}
