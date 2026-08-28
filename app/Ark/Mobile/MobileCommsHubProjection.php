<?php

namespace App\Ark\Mobile;

use App\Models\User;

/**
 * Mobile Comms hub — voice posture, comms pressure, and conversation inbox in one poll.
 */
final class MobileCommsHubProjection
{
    public function __construct(
        private readonly MobileStaffAccess $access,
        private readonly MobileTelephonyDialProjection $telephony,
        private readonly MobileAttentionProjection $attention,
        private readonly MobileConversationsProjection $conversations,
        private readonly MobileInboundCallProjection $inboundCall,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        abort_unless($this->access->canAccessShopCommunications($user), 403);

        $comms = $this->attention->commsForUser($user);
        $threads = $this->conversations->threadsForUser($user);

        return [
            'question' => 'Who needs a response or a call?',
            'telephony' => $this->telephony->shellTelephony($user),
            'active_inbound_call' => $this->inboundCall->activeForUser($user),
            'sections' => $comms['sections'],
            'attention_count' => $comms['total_count'],
            'conversations' => [
                'items' => array_slice($threads['items'], 0, 25),
                'count' => $threads['count'],
            ],
            'poll_after_seconds' => min(
                (int) ($comms['poll_after_seconds'] ?? 30),
                (int) ($threads['poll_after_seconds'] ?? 45),
            ),
        ];
    }
}
