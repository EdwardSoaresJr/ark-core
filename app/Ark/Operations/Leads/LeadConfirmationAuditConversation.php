<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Conversations\ConversationStatus;
use Illuminate\Support\Str;

/**
 * Website lead email confirmations log on a sibling email conversation.
 * Primary shop work stays on the phone thread — audit threads must not inherit Needs shop pressure.
 */
final class LeadConfirmationAuditConversation
{
    public function __construct(
        private readonly ConversationLeadResolver $leadResolver,
    ) {}

    public function isAuditOnly(Conversation $conversation): bool
    {
        // Relation may already be the "latest message" limit(1) hydrate from Attention —
        // always reload the full set when auditing confirmation threads.
        $conversation->unsetRelation('messages');
        $conversation->load(['messages.participant']);

        if ($conversation->messages->isEmpty()) {
            return false;
        }

        return $conversation->messages->every(
            fn ($message): bool => ($message->metadata['website_lead_confirmation'] ?? false) === true
                && $message->direction === OperationalCommunicationDirection::Outbound
                && $message->participant?->participant_type === ConversationParticipantType::System,
        );
    }

    public function suppressFromShopTurn(Conversation $conversation, ?Lead $preloadedLead = null): bool
    {
        if ($conversation->status !== ConversationStatus::Open) {
            return false;
        }

        // Fast path: most shop-turn SMS threads are not confirmation audits.
        if ($this->latestMessageLooksLikeConfirmation($conversation) && $this->isAuditOnly($conversation)) {
            return true;
        }

        if ($conversation->contact_surface !== ConversationContactSurface::Email) {
            return false;
        }

        $lead = $preloadedLead ?? $this->leadResolver->forTurn($conversation);

        return $lead instanceof Lead
            && $lead->first_contacted_at !== null
            && $lead->conversation_id !== null
            && $lead->conversation_id !== $conversation->id;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Conversation>  $conversations
     * @return list<int>
     */
    public function suppressIdsFromShopTurn($conversations): array
    {
        $ids = [];

        foreach ($conversations as $conversation) {
            if ($conversation instanceof Conversation && $this->suppressFromShopTurn($conversation)) {
                $ids[] = $conversation->id;
            }
        }

        return $ids;
    }

    private function latestMessageLooksLikeConfirmation(Conversation $conversation): bool
    {
        $latest = $conversation->relationLoaded('messages')
            ? $conversation->messages->sortByDesc(fn ($message) => $message->occurred_at?->timestamp ?? 0)->first()
            : $conversation->messages()->orderByDesc('occurred_at')->first();

        if ($latest === null) {
            return false;
        }

        return ($latest->metadata['website_lead_confirmation'] ?? false) === true;
    }

    public function resolveAuditThread(Conversation $conversation): void
    {
        if ($conversation->status === ConversationStatus::Resolved) {
            return;
        }

        $conversation->update([
            'status' => ConversationStatus::Resolved,
            'resolved_at' => now(),
            'posture_changed_at' => now(),
        ]);
    }

    public function finalizeEmailConfirmationAudit(Lead $lead, Conversation $emailConversation): void
    {
        if ($lead->conversation_id === null || $lead->conversation_id === $emailConversation->id) {
            return;
        }

        $this->resolveAuditThread($emailConversation);
    }

    public function resolveSiblingAuditsForLead(Lead $lead): void
    {
        $email = $this->normalizedEmail($lead->contact_email);

        if ($email === null || $lead->conversation_id === null) {
            return;
        }

        $emailConversation = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Email)
            ->where('contact_address', $email)
            ->where('status', ConversationStatus::Open)
            ->first();

        if (! $emailConversation instanceof Conversation || $emailConversation->id === $lead->conversation_id) {
            return;
        }

        if ($this->suppressFromShopTurn($emailConversation)) {
            $this->resolveAuditThread($emailConversation);
        }
    }

    private function normalizedEmail(mixed $email): ?string
    {
        $normalized = Str::lower(trim((string) $email));

        return $normalized !== '' ? $normalized : null;
    }
}
