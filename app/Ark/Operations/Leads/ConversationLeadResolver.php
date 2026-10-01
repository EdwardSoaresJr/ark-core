<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use Illuminate\Support\Str;

/**
 * Resolve the lead that owns a conversation thread for turn labels and attention.
 *
 * When SMS reconciliation opens a sibling lead on the same conversation, prefer the
 * lead the shop already contacted (website lead thread continuity).
 *
 * Email confirmation audit threads resolve leads by contact_email when conversation_id differs.
 */
final class ConversationLeadResolver
{
    public function forTurn(Conversation|int $conversation): ?Lead
    {
        $conversationModel = $conversation instanceof Conversation
            ? $conversation
            : Conversation::query()->find($conversation);

        if (! $conversationModel instanceof Conversation) {
            return null;
        }

        return $this->mapForConversations(collect([$conversationModel]))[$conversationModel->id] ?? null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Conversation>  $conversations
     * @return array<int, Lead|null>
     */
    public function mapForConversations($conversations): array
    {
        $models = collect($conversations)->filter(fn ($conversation): bool => $conversation instanceof Conversation);

        if ($models->isEmpty()) {
            return [];
        }

        $ids = $models->pluck('id')->all();
        $leadsByConversationId = Lead::query()
            ->open()
            ->notSpam()
            ->whereIn('conversation_id', $ids)
            ->orderByRaw('CASE WHEN first_contacted_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('conversation_id');

        $emailAddresses = $models
            ->filter(fn (Conversation $conversation): bool => $conversation->contact_surface === ConversationContactSurface::Email)
            ->map(fn (Conversation $conversation): string => Str::lower(trim((string) $conversation->contact_address)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $leadsByEmail = $emailAddresses === []
            ? collect()
            : Lead::query()
                ->open()
                ->notSpam()
                ->whereNotNull('contact_email')
                ->where(function ($query) use ($emailAddresses): void {
                    foreach ($emailAddresses as $email) {
                        $query->orWhereRaw('LOWER(TRIM(contact_email)) = ?', [$email]);
                    }
                })
                ->orderByRaw('CASE WHEN first_contacted_at IS NOT NULL THEN 0 ELSE 1 END')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy(fn (Lead $lead): string => Str::lower(trim((string) $lead->contact_email)));

        $resolved = [];

        foreach ($models as $conversation) {
            $lead = $leadsByConversationId->get($conversation->id)?->first();

            if (! $lead instanceof Lead
                && $conversation->contact_surface === ConversationContactSurface::Email) {
                $email = Str::lower(trim((string) $conversation->contact_address));
                $lead = $email !== '' ? $leadsByEmail->get($email)?->first() : null;
            }

            $resolved[$conversation->id] = $lead instanceof Lead ? $lead : null;
        }

        return $resolved;
    }
}
