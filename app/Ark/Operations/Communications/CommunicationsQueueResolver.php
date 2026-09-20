<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRead;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Telephony\CallQueuePresenter;
use App\Ark\Operations\Telephony\CallQueueResolver;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\IncomingCallContextPresenter;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Attention Queue projection — composes actionable rows from authoritative stores.
 *
 * Not an AttentionItem authority. CallSession, ConversationMessage, and future
 * HandoffNote / estimate-view / voicemail sources remain separate; this resolver
 * projects them for morning triage and live recovery.
 */
class CommunicationsQueueResolver
{
    private const REQUEST_CACHE_PREFIX = 'comms_queue:';

    public function __construct(
        private readonly CallQueueResolver $callQueueResolver,
        private readonly UnreadInboundMessageQueue $unreadInboundMessageQueue,
        private readonly CommunicationsMessageQueuePresenter $messagePresenter,
        private readonly IncomingCallContextPresenter $incomingCallContextPresenter,
        private readonly CallQueuePresenter $callQueuePresenter,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly CommunicationsQueueSinceLastShift $sinceLastShift,
        private readonly InboundMessageQueueChannels $inboundMessageQueueChannels,
        private readonly CommunicationsAttentionDedupe $attentionDedupe,
        private readonly ShopTurnAttentionQueue $shopTurnAttentionQueue,
    ) {}

    /**
     * @return array{
     *     count: int,
     *     summary: array<string, mixed>,
     *     calls: array<int, array<string, mixed>>,
     *     messages: array<int, array<string, mixed>>,
     *     message_channels: array<int, array{channel: string, label: string, count: int, messages: array<int, array<string, mixed>>}>,
     *     items: array<int, array<string, mixed>>,
     *     needs_attention: array<int, array<string, mixed>>,
     *     since_last_shift: array<int, array<string, mixed>>,
     *     since_last_shift_boundary_label: string,
     *     recent_activity: array<int, array<string, mixed>>,
     *     unknown: array<int, array<string, mixed>>,
     *     queue_url: string,
     * }
     */
    public function resolve(?User $viewer = null, ?Carbon $previousLastSeenAt = null): array
    {
        return $this->buildQueue($viewer, $previousLastSeenAt, includeRecentActivity: true);
    }

    /**
     * Actionable queue without the recent-activity feed — used for layout pressure,
     * comms gate, and interrupt polling so every page load does not scan 70+ rows.
     *
     * @return array{
     *     count: int,
     *     summary: array<string, mixed>,
     *     calls: array<int, array<string, mixed>>,
     *     messages: array<int, array<string, mixed>>,
     *     message_channels: array<int, array{channel: string, label: string, count: int, messages: array<int, array<string, mixed>>}>,
     *     items: array<int, array<string, mixed>>,
     *     needs_attention: array<int, array<string, mixed>>,
     *     since_last_shift: array<int, array<string, mixed>>,
     *     since_last_shift_boundary_label: string,
     *     recent_activity: array<int, array<string, mixed>>,
     *     unknown: array<int, array<string, mixed>>,
     *     queue_url: string,
     * }
     */
    public function resolveAttention(?User $viewer = null, ?Carbon $previousLastSeenAt = null): array
    {
        return $this->buildQueue($viewer, $previousLastSeenAt, includeRecentActivity: false);
    }

    /**
     * Empty attention snapshot while station privacy gate is active (no customer PII).
     *
     * @return array<string, mixed>
     */
    public function privacyGatedAttention(): array
    {
        return [
            'count' => 0,
            'summary' => [
                'count' => 0,
                'call_count' => 0,
                'message_count' => 0,
                'since_last_shift_count' => 0,
                'has_live_calls' => false,
                'urgency' => 'idle',
                'breakdown_label' => '',
                'trigger_label' => '',
            ],
            'calls' => [],
            'messages' => [],
            'message_channels' => [],
            'items' => [],
            'needs_attention' => [],
            'since_last_shift' => [],
            'since_last_shift_boundary_label' => '',
            'recent_activity' => [],
            'unknown' => [],
            'queue_url' => Route::has('operations.telephony.call-queue')
                ? route('operations.telephony.call-queue')
                : '',
        ];
    }

    /**
     * @return array{
     *     count: int,
     *     summary: array<string, mixed>,
     *     calls: array<int, array<string, mixed>>,
     *     messages: array<int, array<string, mixed>>,
     *     message_channels: array<int, array{channel: string, label: string, count: int, messages: array<int, array<string, mixed>>}>,
     *     items: array<int, array<string, mixed>>,
     *     needs_attention: array<int, array<string, mixed>>,
     *     since_last_shift: array<int, array<string, mixed>>,
     *     since_last_shift_boundary_label: string,
     *     recent_activity: array<int, array<string, mixed>>,
     *     unknown: array<int, array<string, mixed>>,
     *     queue_url: string,
     * }
     */
    private function buildQueue(?User $viewer, ?Carbon $previousLastSeenAt, bool $includeRecentActivity): array
    {
        $request = request();
        $cacheKey = self::REQUEST_CACHE_PREFIX.sprintf(
            '%s:%s:%d',
            $viewer?->id ?? 'guest',
            $previousLastSeenAt?->timestamp ?? 'null',
            $includeRecentActivity ? 1 : 0,
        );

        if ($request !== null && $request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        $callPayload = $this->callQueueResolver->resolve($viewer);
        $calls = array_map(function (array $call): array {
            $call['kind'] = 'call';
            $call['queue_tab'] = CommunicationsSurfaceChannel::Phone->value;

            return $call;
        }, $callPayload['calls']);

        $messageChannels = $viewer === null
            ? []
            : collect($this->inboundMessageQueueChannels->enabled())
                ->map(function (OperationalCommunicationChannel $channel) use ($viewer): array {
                    $messages = $this->unreadInboundMessageQueue
                        ->latestUnreadPerConversation($viewer, $channel)
                        ->map(fn (ConversationMessage $message): array => $this->messagePresenter->present($message, unread: true))
                        ->values()
                        ->all();

                    return [
                        'channel' => $channel->value,
                        'label' => $channel->label(),
                        'count' => count($messages),
                        'messages' => $messages,
                    ];
                })
                ->values()
                ->all();

        $unreadMessages = collect($messageChannels)
            ->flatMap(fn (array $section): array => $section['messages'])
            ->values()
            ->all();

        $unreadConversationIds = collect($unreadMessages)
            ->pluck('conversation_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $shopTurnRows = $this->shopTurnAttentionQueue->rowsFor($viewer, $unreadConversationIds);

        $needsAttention = $this->attentionDedupe->dedupe(
            $this->sortNeedsAttention(array_merge($calls, $unreadMessages, $shopTurnRows)),
        );
        $sinceLastShift = $this->sinceLastShift->project($needsAttention, $viewer, $previousLastSeenAt);
        $unknown = array_values(array_filter(
            $needsAttention,
            fn (array $row): bool => ! ($row['matched'] ?? false),
        ));
        $recentActivity = $includeRecentActivity ? $this->recentActivity($viewer) : [];
        $items = array_slice($needsAttention, 0, 8);
        $summary = $this->buildSummary($calls, $messageChannels, $sinceLastShift['rows'], count($needsAttention));

        $resolved = [
            'count' => $summary['count'],
            'summary' => $summary,
            'calls' => $calls,
            'messages' => $unreadMessages,
            'message_channels' => $messageChannels,
            'items' => $items,
            'needs_attention' => $needsAttention,
            'since_last_shift' => $sinceLastShift['rows'],
            'since_last_shift_boundary_label' => $sinceLastShift['boundary_label'],
            'recent_activity' => $recentActivity,
            'unknown' => $unknown,
            'queue_url' => Route::has('operations.communications.inbox')
                ? CommunicationsNeedsYou::url()
                : '',
        ];

        $request?->attributes->set($cacheKey, $resolved);

        return $resolved;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentActivityFor(?User $viewer): array
    {
        return $this->recentActivity($viewer);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortNeedsAttention(array $rows): array
    {
        usort($rows, fn (array $left, array $right): int => $this->needsAttentionSortKey($left) <=> $this->needsAttentionSortKey($right));

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function needsAttentionSortKey(array $row): int
    {
        if (($row['kind'] ?? '') === 'call') {
            return match ($row['status'] ?? '') {
                CallSessionStatus::Ringing->value => 0,
                CallSessionStatus::Missed->value, CallSessionStatus::Failed->value => 2,
                CallSessionStatus::Answered->value => 4,
                default => 5,
            };
        }

        if (($row['state'] ?? '') === 'unread' || ($row['state'] ?? '') === 'shop_turn') {
            return 1;
        }

        return 3;
    }

    /**
     * @param  array<int, array<string, mixed>>  $calls
     * @param  array<int, array{channel: string, label: string, count: int, messages: array<int, array<string, mixed>>}>  $messageChannels
     * @param  array<int, array<string, mixed>>  $sinceLastShift
     * @return array{
     *     count: int,
     *     call_count: int,
     *     message_count: int,
     *     since_last_shift_count: int,
     *     has_live_calls: bool,
     *     urgency: string,
     *     breakdown_label: string,
     *     trigger_label: string,
     * }
     */
    private function buildSummary(array $calls, array $messageChannels, array $sinceLastShift, int $dedupedCount): array
    {
        $callCount = count($calls);
        $messageCount = collect($messageChannels)->sum('count');
        $count = $dedupedCount;
        $sinceLastShiftCount = count($sinceLastShift);

        $hasLiveCalls = false;

        foreach ($calls as $call) {
            if (($call['is_actively_live'] ?? false) === true) {
                $hasLiveCalls = true;

                break;
            }
        }

        $breakdownParts = [];

        if ($callCount > 0) {
            $breakdownParts[] = $callCount === 1 ? '1 call' : "{$callCount} calls";
        }

        foreach ($messageChannels as $section) {
            if (($section['count'] ?? 0) === 0) {
                continue;
            }

            $label = (string) ($section['label'] ?? 'Message');
            $sectionCount = (int) $section['count'];
            $breakdownParts[] = $sectionCount === 1 ? "1 {$label}" : "{$sectionCount} {$label}";
        }

        $breakdownLabel = implode(' · ', $breakdownParts);

        $triggerLabel = match (true) {
            $hasLiveCalls => 'Live now',
            $sinceLastShiftCount > 0 => $sinceLastShiftCount === 1
                ? '1 since last shift'
                : "{$sinceLastShiftCount} since last shift",
            $count > 0 => 'Needs you',
            default => '',
        };

        return [
            'count' => $count,
            'call_count' => $callCount,
            'message_count' => $messageCount,
            'since_last_shift_count' => $sinceLastShiftCount,
            'has_live_calls' => $hasLiveCalls,
            'urgency' => $hasLiveCalls ? 'live' : ($count > 0 ? 'attention' : 'idle'),
            'breakdown_label' => $breakdownLabel,
            'trigger_label' => $triggerLabel,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(?User $viewer): array
    {
        $windowStart = now()->subHours(CommunicationsQueueWindow::HOURS);
        $rows = [];

        $sessions = CallSession::query()
            ->with(['customer', 'owner'])
            ->excludingFeatureCodeDials()
            ->where('started_at', '>=', $windowStart)
            ->whereIn('status', [
                CallSessionStatus::Ringing,
                CallSessionStatus::Answered,
                CallSessionStatus::Missed,
                CallSessionStatus::Completed,
                CallSessionStatus::Failed,
            ])
            ->orderByDesc('started_at')
            ->limit(30)
            ->get();

        foreach ($sessions as $session) {
            $context = $session->customer_id !== null
                ? $this->callContextResolver->resolveForCustomer($session->customer)
                : $this->callContextResolver->resolve($session->normalized_from);

            $base = $this->incomingCallContextPresenter->present($session, $context);
            $presented = $this->callQueuePresenter->present($session, $context, $viewer);
            $handled = $session->worked_at !== null;

            $rows[] = array_merge($presented, [
                'kind' => 'call',
                'queue_tab' => CommunicationsSurfaceChannel::Phone->value,
                'channel_label' => 'Call',
                'state' => $handled ? 'handled' : ($session->status->value === CallSessionStatus::Missed->value ? 'missed' : $session->status->value),
                'state_label' => $handled ? 'Handled' : ($base['status_label'] ?? 'Call'),
                'snippet' => $handled
                    ? 'Call handled on the floor'
                    : 'Inbound call · '.($base['display_phone'] ?? ''),
                'occurred_at' => $session->started_at?->toIso8601String(),
                'age_label' => $session->started_at?->diffForHumans(short: true) ?? '',
                'in_needs_attention' => ! $handled && $session->worked_at === null,
                'dropdown_label' => $presented['dropdown_label'] ?? ('Call · '.($handled ? 'Handled' : ($base['status_label'] ?? 'Call')).' · '.($presented['headline'] ?? 'Unknown Caller')),
            ]);
        }

        $readThroughByConversation = $viewer !== null
            ? ConversationRead::query()
                ->where('user_id', $viewer->id)
                ->pluck('read_through_at', 'conversation_id')
            : collect();

        $queueChannels = $this->inboundMessageQueueChannels->enabled();

        $messages = ConversationMessage::query()
            ->with(['conversation', 'attachments', 'participant.customer'])
            ->where('occurred_at', '>=', $windowStart)
            ->whereIn('channel', $queueChannels)
            ->whereHas('conversation', function ($query) use ($queueChannels): void {
                $surfaces = collect($queueChannels)
                    ->map(fn (OperationalCommunicationChannel $channel): ?ConversationContactSurface => $channel->inboundQueueContactSurface())
                    ->filter()
                    ->values()
                    ->all();

                $query->whereIn('contact_surface', $surfaces);
            })
            ->orderByDesc('occurred_at')
            ->limit(40)
            ->get();

        foreach ($messages as $message) {
            $readThrough = $readThroughByConversation->get($message->conversation_id);
            $unread = $message->direction === OperationalCommunicationDirection::Inbound
                && (! $readThrough instanceof Carbon || $message->occurred_at > $readThrough);

            $presented = $this->messagePresenter->present($message, unread: $unread);

            $rows[] = array_merge($presented, [
                'in_needs_attention' => $unread,
            ]);
        }

        usort($rows, function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['occurred_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['occurred_at'] ?? '')) ?: 0;

            return $rightTime <=> $leftTime;
        });

        return array_slice($rows, 0, 40);
    }
}
