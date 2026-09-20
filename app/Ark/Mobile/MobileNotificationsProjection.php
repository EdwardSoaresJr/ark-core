<?php

namespace App\Ark\Mobile;

use App\Models\User;

final class MobileNotificationsProjection
{
    public function __construct(
        private readonly MobileWorkProjection $work,
        private readonly MobileConversationsProjection $conversations,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     count: int,
     *     poll_after_seconds: int,
     * }
     */
    public function forUser(User $user): array
    {
        $items = [];

        foreach ($this->work->forUser($user)['items'] as $card) {
            if (filled($card['attention_reason'] ?? null)) {
                $repairOrderId = isset($card['repair_order_id'])
                    ? (int) MobileRepairOrderRouteId::normalize($card['repair_order_id'])
                    : null;

                $items[] = [
                    'kind' => 'repair_order_attention',
                    'title' => (string) $card['customer_name'],
                    'body' => (string) $card['attention_reason'],
                    'repair_order_id' => $repairOrderId,
                    'vehicle_label' => $card['vehicle_label'] ?? null,
                    'occurred_at' => $card['updated_at'] ?? null,
                    'deep_link' => $repairOrderId !== null
                        ? MobileCompanionDeepLink::repairOrder($repairOrderId)
                        : MobileCompanionDeepLink::home(),
                    'route' => $repairOrderId !== null
                        ? MobileCompanionDeepLink::repairOrder($repairOrderId)
                        : MobileCompanionDeepLink::home(),
                ];
            }
        }

        foreach ($this->conversations->threadsForUser($user)['items'] as $thread) {
            if (($thread['unread_count'] ?? 0) > 0 || ($thread['needs_attention'] ?? false)) {
                $conversationId = (int) ($thread['id'] ?? 0);

                $items[] = [
                    'kind' => 'conversation_attention',
                    'title' => (string) ($thread['headline'] ?? 'Conversation'),
                    'body' => (string) ($thread['preview'] ?? 'Needs response'),
                    'conversation_id' => $conversationId,
                    'vehicle_label' => $thread['vehicle_label'] ?? null,
                    'repair_order_id' => $thread['repair_order_id'] ?? null,
                    'occurred_at' => null,
                    'deep_link' => MobileCompanionDeepLink::conversation($conversationId),
                    'route' => MobileCompanionDeepLink::conversation($conversationId),
                ];
            }
        }

        return [
            'items' => $items,
            'count' => count($items),
            'poll_after_seconds' => 45,
        ];
    }
}
