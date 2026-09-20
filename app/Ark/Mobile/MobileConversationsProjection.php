<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Communications\CommunicationsWorkspaceProjection;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\Projections\ConversationProjection;
use App\Ark\Operations\Conversations\Projections\ConversationSurface;
use App\Ark\Operations\Leads\ConversationLeadResolver;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

/**
 * Mobile Conversations — one chronological customer relationship timeline per conversation.
 */
final class MobileConversationsProjection
{
    private const LIST_POLL_SECONDS = 45;

    public function __construct(
        private readonly MobileStaffAccess $access,
        private readonly CommunicationsWorkspaceProjection $workspace,
        private readonly ConversationProjection $conversationProjection,
        private readonly ConversationLeadResolver $conversationLeads,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     count: int,
     *     poll_after_seconds: int,
     * }
     */
    public function threadsForUser(User $user): array
    {
        if ($this->access->canAccessShopCommunications($user)) {
            $inbox = $this->workspace->inbox($user, null, null, null);

            $items = collect($inbox['list_items'])
                ->filter(fn (array $item): bool => ($item['kind'] ?? '') === 'conversation')
                ->take(40)
                ->map(fn (array $item): array => $this->presentListItem($item))
                ->values()
                ->all();

            $items = $this->attachRecentActivitiesToListItems($items, $user);

            return [
                'items' => $items,
                'count' => count($items),
                'poll_after_seconds' => self::LIST_POLL_SECONDS,
            ];
        }

        $assignedRepairOrderIds = RepairOrder::query()
            ->where('assigned_technician_id', $user->id)
            ->pluck('id');

        if ($assignedRepairOrderIds->isEmpty()) {
            return ['items' => [], 'count' => 0, 'poll_after_seconds' => self::LIST_POLL_SECONDS];
        }

        $conversationIds = ConversationLink::query()
            ->where('linkable_type', (new RepairOrder)->getMorphClass())
            ->whereIn('linkable_id', $assignedRepairOrderIds)
            ->pluck('conversation_id')
            ->unique()
            ->values();

        $items = Conversation::query()
            ->whereIn('id', $conversationIds)
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (Conversation $conversation): array => $this->presentConversationSummary($conversation, $user))
            ->values()
            ->all();

        return [
            'items' => $items,
            'count' => count($items),
            'poll_after_seconds' => self::LIST_POLL_SECONDS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function threadForConversation(User $user, Conversation $conversation): array
    {
        return $this->conversationProjection->thread(
            $user,
            $conversation,
            ConversationSurface::Mobile,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function presentListItem(array $item): array
    {
        $conversationId = (int) str_replace('conversation:', '', (string) ($item['key'] ?? ''));

        $postureLabel = trim((string) ($item['reason'] ?? ''));
        $snippet = trim((string) ($item['snippet'] ?? $item['preview'] ?? ''));
        $attentionReasons = is_array($item['attention_reasons'] ?? null) ? $item['attention_reasons'] : [];

        return [
            'id' => $conversationId,
            'kind' => (string) ($item['kind'] ?? 'conversation'),
            'customer_id' => isset($item['customer_id']) ? (int) $item['customer_id'] : null,
            'headline' => (string) ($item['headline'] ?? 'Unknown'),
            'preview' => $snippet,
            'posture_label' => $postureLabel,
            'activity_label' => (string) ($item['channel_label'] ?? 'Customer activity'),
            'channel_label' => (string) ($item['channel_label'] ?? 'Message'),
            'age_label' => (string) ($item['age_label'] ?? ''),
            'assigned_label' => filled($item['assigned_label'] ?? null) ? (string) $item['assigned_label'] : null,
            'observation_label' => $attentionReasons !== [] ? trim((string) ($attentionReasons[0] ?? '')) : null,
            'needs_attention' => $attentionReasons !== [] || $postureLabel !== '',
            'unread_count' => 0,
            'deep_link' => MobileCompanionDeepLink::conversation($conversationId),
            'route' => MobileCompanionDeepLink::conversation($conversationId),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function enrichAutomotiveContext(array $item, Conversation $conversation): array
    {
        $lead = $this->conversationLeads->forTurn($conversation)?->loadMissing(['repairOrder.vehicle']);
        $repairOrder = $lead?->repairOrder;

        if (! $repairOrder instanceof RepairOrder) {
            $conversation->loadMissing('links.linkable');

            foreach ($conversation->links as $link) {
                if ($link->linkable instanceof RepairOrder) {
                    $repairOrder = $link->linkable->loadMissing('vehicle');
                    break;
                }
            }
        }

        if ($repairOrder instanceof RepairOrder) {
            $item['repair_order_id'] = MobileRepairOrderRouteId::normalize($repairOrder->repair_order_id);
            $item['lifecycle_label'] = $repairOrder->statusDisplayLabel();
            $item['vehicle_label'] = $repairOrder->vehicle?->display_name;
            $item['plate'] = $repairOrder->vehicle?->plate;
            $item['routes'] = [
                'thread' => MobileCompanionDeepLink::conversation($conversation->id),
                'repair_order' => MobileCompanionDeepLink::repairOrder((int) $repairOrder->repair_order_id),
            ];
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentConversationSummary(Conversation $conversation, User $user): array
    {
        $summary = $this->conversationProjection->listItem($user, $conversation);
        $recent = $summary['recent_activities'];
        $latest = is_array($recent) && $recent !== [] ? $recent[0] : null;

        $item = [
            'id' => $conversation->id,
            'kind' => 'conversation',
            'customer_id' => $conversation->participants()
                ->whereNotNull('customer_id')
                ->value('customer_id'),
            'headline' => (string) ($summary['headline'] ?? 'Unknown'),
            'preview' => is_array($latest)
                ? (string) (($latest['summary'] ?? '') !== '' ? $latest['summary'] : ($latest['activity_label'] ?? ''))
                : '',
            'posture_label' => $conversation->waiting_on?->label() ?? '',
            'activity_label' => is_array($latest) ? (string) ($latest['activity_label'] ?? 'Customer activity') : 'Customer activity',
            'channel_label' => is_array($latest) ? (string) ($latest['category_label'] ?? 'Activity') : 'Activity',
            'age_label' => (string) ($summary['last_activity']['label'] ?? ''),
            'assigned_label' => $conversation->owner?->name,
            'observation_label' => null,
            'needs_attention' => false,
            'unread_count' => (int) ($summary['unread_count'] ?? 0),
            'recent_activities' => $recent,
            'recent_events' => $recent,
            'deep_link' => MobileCompanionDeepLink::conversation($conversation->id),
            'route' => MobileCompanionDeepLink::conversation($conversation->id),
        ];

        return $this->finalizeConversationListItem($user, $conversation, $item);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function attachRecentActivitiesToListItems(array $items, User $user): array
    {
        $ids = collect($items)->pluck('id')->filter()->values()->all();

        if ($ids === []) {
            return $items;
        }

        $conversations = Conversation::query()
            ->whereIn('id', $ids)
            ->with(['owner:id,name'])
            ->get()
            ->keyBy('id');

        return array_map(function (array $item) use ($conversations, $user): array {
            $conversation = $conversations->get($item['id'] ?? null);

            if ($conversation instanceof Conversation) {
                $item = $this->finalizeConversationListItem($user, $conversation, $item);
            }

            return $item;
        }, $items);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function finalizeConversationListItem(User $user, Conversation $conversation, array $item): array
    {
        $summary = $this->conversationProjection->listItem($user, $conversation);
        $recent = $summary['recent_activities'];
        $item['recent_activities'] = $recent;
        $item['recent_events'] = $recent;
        $item['unread_count'] = (int) ($summary['unread_count'] ?? 0);

        if (blank($item['posture_label'] ?? null)) {
            $item['posture_label'] = $conversation->waiting_on?->label() ?? '';
        }

        if (blank($item['assigned_label'] ?? null) && filled($conversation->owner?->name)) {
            $item['assigned_label'] = (string) $conversation->owner->name;
        }

        $item = $this->enrichListItemFromTimeline($item);
        $item = $this->enrichAutomotiveContext($item, $conversation);

        $item['needs_attention'] = $item['unread_count'] > 0
            || $conversation->waiting_on === ConversationWaitingOn::Shop;

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function enrichListItemFromTimeline(array $item): array
    {
        $activities = is_array($item['recent_activities'] ?? null) ? $item['recent_activities'] : [];

        if ($activities === []) {
            return $item;
        }

        $latest = $activities[0];

        if (! is_array($latest)) {
            return $item;
        }

        $preview = filled($latest['summary'] ?? null)
            ? (string) $latest['summary']
            : (string) ($latest['activity_label'] ?? $item['preview'] ?? '');

        if ($preview !== '') {
            $item['preview'] = $preview;
        }

        $item['activity_label'] = (string) ($latest['activity_label'] ?? 'Customer activity');
        $item['channel_label'] = (string) ($latest['category_label'] ?? 'Activity');

        return $item;
    }
}
