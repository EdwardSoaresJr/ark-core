<?php

namespace App\Ark\Operations\Conversations\Projections;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;

/**
 * Live conversation state scaffold — ARK Voice will populate controls later.
 */
final class ConversationLiveStateProjection
{
    /**
     * @return array<string, mixed>|null
     */
    public function forConversation(Conversation $conversation): ?array
    {
        if ($conversation->contact_surface !== ConversationContactSurface::Phone) {
            return null;
        }

        $normalizedPhone = PhoneNumber::normalize((string) $conversation->contact_address);

        if ($normalizedPhone === null) {
            return null;
        }

        $session = CallSession::query()
            ->where(function ($query) use ($normalizedPhone): void {
                $query->where('normalized_from', $normalizedPhone)
                    ->orWhere('normalized_to', $normalizedPhone);
            })
            ->whereIn('status', [
                CallSessionStatus::Ringing->value,
                CallSessionStatus::Answered->value,
            ])
            ->latest('started_at')
            ->first();

        if (! $session instanceof CallSession || ! $session->isActivelyLive()) {
            return null;
        }

        $startedAt = $session->answered_at ?? $session->started_at;

        return [
            'mode' => 'call',
            'status' => $session->status->value,
            'headline' => 'Live call',
            'call_session_id' => $session->id,
            'duration_seconds' => $startedAt !== null ? (int) $startedAt->diffInSeconds(now()) : 0,
            'controls' => [
                ['key' => 'mute', 'label' => 'Mute', 'enabled' => false],
                ['key' => 'transfer', 'label' => 'Transfer', 'enabled' => false],
                ['key' => 'page_technician', 'label' => 'Page tech', 'enabled' => false],
                ['key' => 'open_ro', 'label' => 'Open RO', 'enabled' => $session->repair_order_id !== null],
                ['key' => 'record_note', 'label' => 'Record note', 'enabled' => true],
            ],
        ];
    }
}
