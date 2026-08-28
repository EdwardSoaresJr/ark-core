<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Attention\CommunicationsAttentionNudgeProjection;
use App\Ark\Operations\Attention\ConversationAttentionCandidateBuilder;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Leads\ConversationLeadResolver;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadConfirmationAuditConversation;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\InboundCallerDisplayPhone;
use App\Ark\Operations\Telephony\IncomingCallContextPresenter;
use App\Ark\Operations\Telephony\TelephonyExtensionLegDial;
use App\Ark\Operations\Timeline\UnifiedOperationalTimeline;
use App\Ark\Runtime\Database\SchemaPresence;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Read-only Communications workspace shell — lists, thread preview, context panel.
 *
 * Not message authority. Composes Conversation, Lead, CallSession, InternalChannel.
 */
final class CommunicationsWorkspaceProjection
{
    public function __construct(
        private readonly CommunicationsQueueResolver $queueResolver,
        private readonly ConversationWorkboardPresenter $conversationPresenter,
        private readonly CommunicationsWorkspaceContextBuilder $contextBuilder,
        private readonly CommunicationsWorkspaceIdentityProjection $identityProjection,
        private readonly UnifiedOperationalTimeline $timeline,
        private readonly ConversationAttentionCandidateBuilder $attentionCandidateBuilder,
        private readonly CustomerCallContextResolver $callContextResolver,
        private readonly IncomingCallContextPresenter $incomingCallContextPresenter,
        private readonly InboundCallerDisplayPhone $callerDisplayPhone,
        private readonly CommunicationsAttentionNudgeProjection $nudges,
        private readonly CommunicationsAnalysisInsightProjection $analysisInsight,
        private readonly CommunicationsAttentionDedupe $attentionDedupe,
        private readonly CommunicationsWorkspaceListDedupe $listDedupe,
        private readonly CommunicationsHistoryQuery $historyQuery,
        private readonly LeadConfirmationAuditConversation $confirmationAudit,
        private readonly ConversationLeadResolver $conversationLeadResolver,
    ) {}

    /**
     * @return array{
     *     section: string,
     *     list_items: list<array<string, mixed>>,
     *     list_count: int,
     *     selected: array<string, mixed>|null,
     *     thread: array<string, mixed>|null,
     *     context: array<string, mixed>|null,
     * }
     */
    public function attention(?User $viewer, ?Carbon $previousLastSeenAt, ?string $selectedKey): array
    {
        if ($viewer === null) {
            return $this->emptySection('attention');
        }

        $queue = $this->queueResolver->resolveAttention($viewer, $previousLastSeenAt);
        $rows = is_array($queue['needs_attention'] ?? null) ? $queue['needs_attention'] : [];
        $rows = $this->attentionDedupe->dedupe($rows);

        $listItems = [];

        foreach (array_values($rows) as $index => $row) {
            // List pressure uses placeholder scores — full AttentionCandidate builds
            // timeline + observations (N+1). Enrich only the selected conversation.
            $listItems[] = $this->attentionListItem($row, $index);
        }

        usort($listItems, fn (array $left, array $right): int => ($right['pressure_score'] ?? 0) <=> ($left['pressure_score'] ?? 0));

        $listItems = $this->withListPreviews($listItems);

        [$listItems, $selected] = $this->resolveSelectionWithList($listItems, $selectedKey, 'attention');
        $thread = $this->threadForSelection($selected, 'attention');
        $context = $this->contextForSelection($selected);
        [$context, $thread] = $this->enrichSelectedAttention($selected, $viewer, $context, $thread);

        return [
            'section' => 'attention',
            'list_items' => $listItems,
            'list_count' => count($listItems),
            'selected' => $selected,
            'thread' => $thread,
            'context' => $context,
        ];
    }

    /**
     * @return array{
     *     section: string,
     *     list_items: list<array<string, mixed>>,
     *     list_count: int,
     *     selected: array<string, mixed>|null,
     *     thread: array<string, mixed>|null,
     *     context: array<string, mixed>|null,
     * }
     */
    public function inbox(
        ?User $viewer,
        ?int $conversationId,
        ?int $leadId,
        ?int $callSessionId,
        ?string $turnFilter = null,
        ?Carbon $previousLastSeenAt = null,
        ?string $listFilter = null,
    ): array {
        if ($viewer === null || ! SchemaPresence::hasTable('conversations')) {
            return $this->emptySection('inbox');
        }

        $filter = $this->normalizeListFilter($listFilter, $turnFilter);
        $filterCounts = $this->cheapFilterCounts($viewer, $previousLastSeenAt);

        $listItems = match ($filter) {
            'waiting' => $this->waitingListItems(),
            'resolved' => $this->resolvedListItems(),
            'all' => $this->allActiveListItems($viewer, $previousLastSeenAt),
            default => $this->needsAttentionListItems($viewer, $previousLastSeenAt),
        };

        $listItems = $this->listDedupe->dedupe($listItems);
        $listItems = $this->enrichListIdentities($listItems);
        $listItems = $this->withListPreviews($listItems);

        $selectedKey = match (true) {
            $conversationId !== null => 'conversation:'.$conversationId,
            $leadId !== null => 'lead:'.$leadId,
            $callSessionId !== null => $this->conversationKeyForCallSession($callSessionId) ?? 'call:'.$callSessionId,
            default => null,
        };

        [$listItems, $selected] = $this->resolveSelectionWithList($listItems, $selectedKey, 'inbox');
        $thread = $this->threadForSelection($selected, 'inbox');
        $context = $this->contextForSelection($selected);
        [$context, $thread] = $this->enrichSelectedAttention($selected, $viewer, $context, $thread);

        return [
            'section' => 'inbox',
            'list_items' => $listItems,
            'list_count' => count($listItems),
            'selected' => $selected,
            'thread' => $thread,
            'context' => $context,
            'list_filter' => $filter,
            'filter_counts' => $filterCounts,
            // Compatibility aliases for older nav / deep links.
            'turn_filter' => match ($filter) {
                'waiting' => 'customer',
                'needs' => 'shop',
                default => null,
            },
            'turn_counts' => [
                'shop' => $filterCounts['needs'],
                'customer' => $filterCounts['waiting'],
                'all' => $filterCounts['all'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function needsAttentionListItems(User $viewer, ?Carbon $previousLastSeenAt): array
    {
        $queue = $this->queueResolver->resolveAttention($viewer, $previousLastSeenAt);
        $rows = is_array($queue['needs_attention'] ?? null) ? $queue['needs_attention'] : [];
        $rows = $this->attentionDedupe->dedupe($rows);

        $listItems = [];

        foreach (array_values($rows) as $index => $row) {
            $listItems[] = $this->attentionListItem($row, $index, 'shop');
        }

        $listItems = $this->threadCallRowsIntoConversations($listItems);

        usort($listItems, fn (array $left, array $right): int => ($right['pressure_score'] ?? 0) <=> ($left['pressure_score'] ?? 0));

        return $listItems;
    }

    /**
     * The left list holds relationships, not per-event rows. A call from a
     * phone with an open conversation is that conversation's pressure — the
     * row keeps the call's reason but selects the conversation.
     *
     * @param  list<array<string, mixed>>  $listItems
     * @return list<array<string, mixed>>
     */
    private function threadCallRowsIntoConversations(array $listItems): array
    {
        $phones = [];

        foreach ($listItems as $item) {
            if (($item['kind'] ?? '') === 'call' && filled($item['normalized_phone'] ?? null)) {
                $phones[] = (string) $item['normalized_phone'];
            }
        }

        if ($phones === []) {
            return $listItems;
        }

        $conversationByPhone = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->where('status', ConversationStatus::Open->value)
            ->whereIn('contact_address', array_values(array_unique($phones)))
            ->pluck('id', 'contact_address');

        return array_map(function (array $item) use ($conversationByPhone): array {
            if (($item['kind'] ?? '') !== 'call') {
                return $item;
            }

            // Live calls stay call rows — the interrupt state is the point.
            if (in_array($item['call_status'] ?? '', [CallSessionStatus::Ringing->value, CallSessionStatus::Answered->value], true)) {
                return $item;
            }

            $conversationId = $conversationByPhone->get((string) ($item['normalized_phone'] ?? ''));

            if ($conversationId === null) {
                return $item;
            }

            $key = 'conversation:'.$conversationId;
            $item['kind'] = 'conversation';
            $item['key'] = $key;
            $item['select_url'] = route('operations.communications.inbox', $this->selectionQuery($key, filter: 'needs'));

            return $item;
        }, $listItems);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function waitingListItems(): array
    {
        $items = $this->inboxConversationItems(ConversationWaitingOn::Customer);

        usort($items, fn (array $left, array $right): int => strcmp(
            (string) ($right['sort_at'] ?? ''),
            (string) ($left['sort_at'] ?? ''),
        ));

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allActiveListItems(User $viewer, ?Carbon $previousLastSeenAt): array
    {
        $needs = $this->needsAttentionListItems($viewer, $previousLastSeenAt);
        $waiting = $this->waitingListItems();
        $needsKeys = collect($needs)->pluck('key')->filter()->all();

        foreach ($waiting as $item) {
            if (! in_array($item['key'] ?? null, $needsKeys, true)) {
                $needs[] = $item;
            }
        }

        usort($needs, function (array $left, array $right): int {
            $leftShop = ($left['turn'] ?? '') === 'shop' || ($left['needs_attention'] ?? false);
            $rightShop = ($right['turn'] ?? '') === 'shop' || ($right['needs_attention'] ?? false);

            if ($leftShop !== $rightShop) {
                return $leftShop ? -1 : 1;
            }

            return ($right['pressure_score'] ?? 0) <=> ($left['pressure_score'] ?? 0)
                ?: strcmp((string) ($right['sort_at'] ?? ''), (string) ($left['sort_at'] ?? ''));
        });

        return $needs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolvedListItems(): array
    {
        return Conversation::query()
            ->where('status', ConversationStatus::Resolved->value)
            ->with([
                'owner:id,name',
                'messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1),
            ])
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get()
            ->map(function (Conversation $conversation): array {
                $presented = $this->conversationPresenter->present($conversation, 'waiting_customer');
                $key = 'conversation:'.$conversation->id;
                $phone = $conversation->contact_surface === ConversationContactSurface::Phone
                    ? (PhoneNumber::display((string) $conversation->contact_address) ?? (string) $conversation->contact_address)
                    : null;

                return [
                    'key' => $key,
                    'kind' => 'conversation',
                    'headline' => (string) ($presented['headline'] ?? $phone ?? 'Unknown contact'),
                    'subtitle' => $phone ?? '',
                    'phone' => $phone,
                    'snippet' => (string) ($presented['snippet'] ?? ''),
                    'channel_label' => (string) ($presented['channel_label'] ?? 'Message'),
                    'reason' => 'Resolved',
                    'turn' => 'customer',
                    'needs_attention' => false,
                    'age_label' => (string) ($presented['posture_age_label'] ?? ''),
                    'pressure_score' => null,
                    'assigned_label' => filled($conversation->owner?->name) ? (string) $conversation->owner->name : null,
                    'customer_id' => $presented['customer_id'] ?? null,
                    'normalized_phone' => $conversation->contact_surface === ConversationContactSurface::Phone
                        ? (string) $conversation->contact_address
                        : null,
                    'select_url' => route('operations.communications.inbox', $this->selectionQuery($key, filter: 'resolved')),
                    'sort_at' => ($conversation->updated_at)?->toIso8601String() ?? '',
                ];
            })
            ->all();
    }

    /**
     * @return array{
     *     section: string,
     *     list_items: list<array<string, mixed>>,
     *     list_count: int,
     *     selected: array<string, mixed>|null,
     *     thread: array<string, mixed>|null,
     *     context: array<string, mixed>|null,
     *     filters: array<string, string>,
     *     paginator: LengthAwarePaginator<int, CallSession>,
     * }
     */
    public function history(
        Request $request,
        ?int $conversationId,
        ?int $callSessionId,
    ): array {
        $filters = $this->historyQuery->filters($request);
        $paginator = $this->historyQuery->paginateCalls($request);

        $listItems = collect($paginator->items())
            ->map(fn (CallSession $session): array => $this->historyQuery->presentCallListItem($session))
            ->all();

        if ($filters['q'] !== '') {
            $listItems = array_merge(
                $this->historyQuery->conversationMatches($request),
                $listItems,
            );
        }

        $listItems = $this->withListPreviews($listItems);

        $selectedKey = match (true) {
            $conversationId !== null => 'conversation:'.$conversationId,
            $callSessionId !== null => 'call:'.$callSessionId,
            default => null,
        };

        [$listItems, $selected] = $this->resolveSelectionWithList($listItems, $selectedKey, 'history');
        $thread = $this->threadForSelection($selected, 'history');
        $context = $this->contextForSelection($selected);

        return [
            'section' => 'history',
            'list_items' => $listItems,
            'list_count' => $paginator->total(),
            'selected' => $selected,
            'thread' => $thread,
            'context' => $context,
            'filters' => $filters,
            'paginator' => $paginator,
        ];
    }

    /**
     * @return array{
     *     section: string,
     *     list_items: list<array<string, mixed>>,
     *     list_count: int,
     *     channels: list<array<string, mixed>>,
     *     selected: array<string, mixed>|null,
     *     thread: array<string, mixed>|null,
     *     context: array<string, mixed>|null,
     * }
     */
    public function internal(?InternalChannel $channel = null): array
    {
        $channels = InternalChannel::query()
            ->orderBy('name')
            ->get()
            ->map(fn (InternalChannel $row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'description' => $row->description,
                'url' => route('operations.communications.internal.channel', $row),
                'channel_label' => 'Internal',
                'subtitle' => $row->description ?? '',
            ])
            ->all();

        $listItems = $this->withListPreviews($channels);

        $selected = $channel !== null
            ? [
                'key' => 'internal:'.$channel->slug,
                'kind' => 'internal',
                'headline' => $channel->name,
                'subtitle' => $channel->description ?? 'Internal channel',
                'channel_label' => 'Internal',
                'reason' => 'Shop coordination',
                'age_label' => '',
                'pressure_score' => null,
                'assigned_label' => null,
                'select_url' => route('operations.communications.internal.channel', $channel),
            ]
            : null;

        $thread = $channel !== null ? $this->internalThread($channel) : null;
        $context = $channel !== null ? $this->internalContext($channel) : null;

        return [
            'section' => 'internal',
            'list_items' => $listItems,
            'list_count' => count($listItems),
            'channels' => $channels,
            'selected' => $selected,
            'thread' => $thread,
            'context' => $context,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function attentionListItem(array $row, int $index, string $turn = 'shop'): array
    {
        $kind = (string) ($row['kind'] ?? 'message');
        $key = match ($kind) {
            'call' => 'call:'.($row['call_session_id'] ?? 0),
            default => 'conversation:'.($row['conversation_id'] ?? 0),
        };

        $reason = match ($kind) {
            'call' => ($row['status_label'] ?? 'Call').' · '.($row['state_label'] ?? 'Needs action'),
            default => (string) ($row['turn_label'] ?? $row['state_label'] ?? 'Needs response'),
        };

        $phone = (string) ($row['display_phone'] ?? '');
        $headline = (string) ($row['headline'] ?? '');
        if ($headline === '' || in_array(strtolower($headline), ['unknown', 'unknown caller', 'unknown contact'], true)) {
            $headline = $phone !== '' ? $phone : 'Unknown contact';
        }

        return [
            'key' => $key,
            'kind' => $kind === 'call' ? 'call' : 'conversation',
            'call_status' => $kind === 'call' ? (string) ($row['status'] ?? '') : null,
            'headline' => $headline,
            'subtitle' => $phone,
            'phone' => $phone !== '' ? $phone : null,
            'snippet' => (string) ($row['snippet'] ?? ''),
            'channel_label' => (string) ($row['channel_label'] ?? ($kind === 'call' ? 'Call' : 'Message')),
            'origin_label' => ! empty($row['website_lead'])
                || str_contains(strtolower((string) ($row['channel_label'] ?? '')), 'lead')
                ? (string) ($row['channel_label'] ?? '')
                : null,
            'reason' => $reason,
            'turn' => $turn,
            'needs_attention' => true,
            'age_label' => (string) ($row['age_label'] ?? $row['posture_age_label'] ?? ''),
            'pressure_score' => $this->placeholderPressureScore($row, $index),
            'assigned_label' => filled($row['owned_by_name'] ?? null)
                ? (string) $row['owned_by_name']
                : null,
            'customer_id' => $row['customer_id'] ?? null,
            'normalized_phone' => $row['normalized_phone'] ?? PhoneNumber::normalize($phone),
            'select_url' => route('operations.communications.inbox', $this->selectionQuery($key, filter: 'needs')),
            'sort_at' => (string) ($row['occurred_at'] ?? now()->toIso8601String()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inboxConversationItems(?ConversationWaitingOn $waitingOn = null): array
    {
        $query = Conversation::query()
            ->where('status', ConversationStatus::Open->value)
            ->with([
                'owner:id,name',
                'messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1),
            ])
            ->orderByDesc('updated_at')
            ->limit(40);

        if ($waitingOn !== null) {
            $query->where('waiting_on', $waitingOn->value);
        }

        return $query
            ->get()
            ->reject(fn (Conversation $conversation): bool => $this->confirmationAudit->suppressFromShopTurn($conversation))
            ->pipe(function ($conversations) {
                $leadsByConversationId = $this->conversationLeadResolver->mapForConversations($conversations);

                return $conversations->map(function (Conversation $conversation) use ($leadsByConversationId): array {
                    $presented = $this->conversationPresenter->present(
                        $conversation,
                        $conversation->waiting_on === ConversationWaitingOn::Shop ? 'needs_shop' : 'waiting_customer',
                    );
                    $key = 'conversation:'.$conversation->id;
                    $ownerName = $conversation->owner?->name;
                    $turnReason = app(ConversationTurnReason::class)->for($conversation);
                    $turn = $conversation->waiting_on === ConversationWaitingOn::Shop ? 'shop' : 'customer';
                    $shopHint = trim(implode(' · ', array_filter([
                        (string) ($presented['vehicle_label'] ?? ''),
                        (string) ($presented['ro_label'] ?? $presented['context_summary'] ?? ''),
                    ])));

                    $phone = (string) ($presented['display_phone'] ?? '');
                    $headline = (string) ($presented['headline'] ?? '');
                    if ($headline === '' || in_array(strtolower($headline), ['unknown', 'unknown caller', 'unknown contact'], true)) {
                        $headline = $phone !== '' ? $phone : 'Unknown contact';
                    }

                    $lead = $leadsByConversationId[$conversation->id] ?? null;
                    $originLabel = $lead instanceof Lead ? $lead->source->opportunityLabel() : null;
                    $channelLabel = $originLabel
                        ?? (string) ($presented['channel_label'] ?? 'Message');

                    return [
                        'key' => $key,
                        'kind' => 'conversation',
                        'headline' => $headline,
                        'subtitle' => $phone,
                        'phone' => $phone !== '' ? $phone : null,
                        'shop_hint' => $shopHint !== '' ? $shopHint : null,
                        'snippet' => (string) ($presented['snippet'] ?? ''),
                        'channel_label' => $channelLabel,
                        'origin_label' => $originLabel,
                        'reason' => (string) ($turnReason['turn_label'] ?? $presented['waiting_on_label'] ?? 'Open'),
                        'turn' => $turn,
                        'needs_attention' => $turn === 'shop',
                        'age_label' => (string) ($presented['posture_age_label'] ?? ''),
                        'pressure_score' => null,
                        'assigned_label' => filled($ownerName) ? (string) $ownerName : null,
                        'customer_id' => $presented['customer_id'] ?? null,
                        'normalized_phone' => $conversation->contact_surface === ConversationContactSurface::Phone
                            ? (string) $conversation->contact_address
                            : null,
                        'select_url' => route('operations.communications.inbox', $this->selectionQuery(
                            $key,
                            filter: $turn === 'shop' ? 'needs' : 'waiting',
                        )),
                        'sort_at' => ($conversation->posture_changed_at ?? $conversation->updated_at)?->toIso8601String() ?? '',
                    ];
                });
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inboxCallItems(): array
    {
        if (! SchemaPresence::hasTable('call_sessions')) {
            return [];
        }

        return CallSession::query()
            ->with(['customer', 'owner:id,name'])
            ->excludingFeatureCodeDials()
            ->where('started_at', '>=', now()->subDays(7))
            ->whereIn('status', [
                CallSessionStatus::Ringing,
                CallSessionStatus::Missed,
                CallSessionStatus::Answered,
                CallSessionStatus::Completed,
            ])
            ->orderByDesc('started_at')
            ->limit(40)
            ->get()
            ->reject(fn (CallSession $session): bool => TelephonyExtensionLegDial::isInboxGhostSession($session))
            ->map(function (CallSession $session): array {
                $key = 'call:'.$session->id;
                $presented = $this->presentCallSession($session);
                $handled = $session->worked_at !== null;
                $identity = $this->callListIdentity($session);

                $missed = ! $handled && $presented['status_label'] === 'Missed';

                return [
                    'key' => $key,
                    'kind' => 'call',
                    'headline' => $presented['headline'],
                    'subtitle' => $presented['display_phone'],
                    'channel_label' => 'Phone',
                    'reason' => match (true) {
                        $handled => 'Handled · '.$presented['status_label'],
                        $missed => 'Missed inbound call',
                        default => $presented['status_label'],
                    },
                    'turn' => $handled ? 'customer' : 'shop',
                    'age_label' => $session->started_at?->diffForHumans(short: true) ?? '',
                    'pressure_score' => null,
                    'assigned_label' => filled($session->owned_by_user_id)
                        ? ($session->owner?->name ?? null)
                        : null,
                    'customer_id' => $identity['customer_id'],
                    'normalized_phone' => $identity['normalized_phone'],
                    'select_url' => route(
                        'operations.communications.inbox',
                        $this->selectionQuery($key, $handled ? 'customer' : 'shop'),
                    ),
                    'sort_at' => $session->started_at?->toIso8601String() ?? '',
                ];
            })
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $listItems
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}
     */
    private function resolveSelectionWithList(array $listItems, ?string $selectedKey, string $section): array
    {
        if ($selectedKey === null) {
            return [$listItems, $listItems[0] ?? null];
        }

        foreach ($listItems as $item) {
            if (($item['key'] ?? '') === $selectedKey) {
                return [$listItems, $item];
            }
        }

        $synthesized = $this->synthesizeListItem($selectedKey, $section);

        if ($synthesized !== null) {
            if ($section === 'inbox' && ($synthesized['kind'] ?? '') === 'call') {
                $conversationItem = $this->matchingConversationListItem($listItems, $synthesized);

                if ($conversationItem !== null) {
                    return [$listItems, $conversationItem];
                }
            }

            array_unshift($listItems, $synthesized);

            return [$listItems, $synthesized];
        }

        return [$listItems, $listItems[0] ?? null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function synthesizeListItem(string $selectedKey, string $section): ?array
    {
        if (! str_contains($selectedKey, ':')) {
            return null;
        }

        [$kind, $id] = explode(':', $selectedKey, 2);
        $entityId = (int) $id;

        if ($entityId <= 0) {
            return null;
        }

        return match ($kind) {
            'call' => $this->synthesizeCallListItem($entityId, $section),
            'conversation' => $this->synthesizeConversationListItem($entityId, $section),
            'lead' => $this->synthesizeLeadListItem($entityId, $section),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function synthesizeCallListItem(int $callSessionId, string $section): ?array
    {
        $session = CallSession::query()
            ->with(['customer', 'owner:id,name'])
            ->find($callSessionId);

        if ($session === null) {
            return null;
        }

        $key = 'call:'.$session->id;
        $presented = $this->presentCallSession($session);
        $handled = $session->worked_at !== null;
        $identity = $this->callListIdentity($session);

        return [
            'key' => $key,
            'kind' => 'call',
            'headline' => $presented['headline'],
            'subtitle' => $presented['display_phone'],
            'channel_label' => 'Phone',
            'reason' => $handled
                ? 'Handled · '.$presented['status_label']
                : $presented['status_label'],
            'age_label' => $session->started_at?->diffForHumans(short: true) ?? '',
            'pressure_score' => null,
            'assigned_label' => filled($session->owned_by_user_id)
                ? ($session->owner?->name ?? null)
                : null,
            'customer_id' => $identity['customer_id'],
            'normalized_phone' => $identity['normalized_phone'],
            'select_url' => $this->selectionUrl($section, $key),
            'sort_at' => $session->started_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function synthesizeConversationListItem(int $conversationId, string $section): ?array
    {
        $conversation = Conversation::query()
            ->with(['owner:id,name'])
            ->find($conversationId);

        if ($conversation === null) {
            return null;
        }

        $presented = $this->conversationPresenter->present(
            $conversation,
            $conversation->waiting_on === ConversationWaitingOn::Shop ? 'needs_shop' : 'waiting_customer',
        );
        $key = 'conversation:'.$conversation->id;

        return [
            'key' => $key,
            'kind' => 'conversation',
            'headline' => (string) ($presented['headline'] ?? 'Unknown'),
            'subtitle' => (string) ($presented['display_phone'] ?? ''),
            'snippet' => (string) ($presented['snippet'] ?? ''),
            'channel_label' => (string) ($presented['channel_label'] ?? 'Message'),
            'reason' => (string) ($presented['waiting_on_label'] ?? $conversation->status->label()),
            'age_label' => (string) ($presented['posture_age_label'] ?? ''),
            'pressure_score' => null,
            'assigned_label' => filled($conversation->owner?->name) ? (string) $conversation->owner->name : null,
            'select_url' => $this->selectionUrl($section, $key),
            'sort_at' => ($conversation->posture_changed_at ?? $conversation->updated_at)?->toIso8601String() ?? '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function synthesizeLeadListItem(int $leadId, string $section): ?array
    {
        if (! SchemaPresence::hasTable('leads')) {
            return null;
        }

        $lead = Lead::query()->find($leadId);

        if ($lead === null) {
            return null;
        }

        $key = 'lead:'.$lead->id;

        return [
            'key' => $key,
            'kind' => 'lead',
            'headline' => filled($lead->contact_name) ? (string) $lead->contact_name : 'Unknown lead',
            'subtitle' => PhoneNumber::display((string) $lead->contact_phone) ?? (string) $lead->contact_phone,
            'channel_label' => $lead->source->label(),
            'reason' => $lead->state->label(),
            'age_label' => $lead->created_at?->diffForHumans(short: true) ?? '',
            'pressure_score' => null,
            'assigned_label' => filled($lead->assigned_user_id) ? 'Assigned' : null,
            'select_url' => $this->selectionUrl($section, $key),
            'sort_at' => $lead->created_at?->toIso8601String() ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $selected
     * @return array<string, mixed>|null
     */
    private function threadForSelection(?array $selected, string $section): ?array
    {
        if ($selected === null) {
            return null;
        }

        return match ($selected['kind'] ?? '') {
            'conversation' => $this->conversationThread((int) Str::after($selected['key'], 'conversation:'), $section),
            'lead' => $this->leadThread((int) Str::after($selected['key'], 'lead:')),
            'call' => $this->callThread((int) Str::after($selected['key'], 'call:'), $section),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $selected
     * @return array<string, mixed>|null
     */
    private function contextForSelection(?array $selected): ?array
    {
        if ($selected === null) {
            return null;
        }

        return match ($selected['kind'] ?? '') {
            'conversation' => $this->contextBuilder->forConversation(
                Conversation::query()->findOrFail((int) Str::after($selected['key'], 'conversation:')),
            ),
            'lead' => $this->contextBuilder->forLead(
                Lead::query()->findOrFail((int) Str::after($selected['key'], 'lead:')),
            ),
            'call' => $this->contextBuilder->forCallSession(
                CallSession::query()->with(['customer', 'repairOrder.vehicle', 'owner:id,name'])->findOrFail((int) Str::after($selected['key'], 'call:')),
            ),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function conversationThread(int $conversationId, string $section): ?array
    {
        $conversation = Conversation::query()->with(['owner:id,name'])->find($conversationId);

        if ($conversation === null) {
            return null;
        }

        $events = array_reverse($this->timeline->forConversationRelationship($conversation, 50)->all());

        $phone = $conversation->contact_surface === ConversationContactSurface::Phone
            ? trim((string) $conversation->contact_address)
            : null;
        $displayPhone = $phone !== null && $phone !== ''
            ? (PhoneNumber::display($phone) ?? $phone)
            : (string) $conversation->contact_address;

        $composerContext = $this->contextBuilder->conversationComposerContext($conversation);
        $turnReason = app(ConversationTurnReason::class)->for($conversation, $composerContext['lead'] ?? null);
        $identity = $this->identityProjection->forConversation(
            $conversation,
            callContext: null,
            lead: $composerContext['lead'] ?? null,
            turnReason: (string) ($turnReason['turn_label'] ?? null),
        );

        return [
            'title' => $identity['name'] ?? $displayPhone,
            'subtitle' => $identity['turn_label'] ?? null,
            'status_label' => $conversation->status->label(),
            'assignment_label' => $conversation->owner?->name ?? 'Unassigned',
            'identity' => $identity,
            'events' => $events,
            'empty_label' => 'No messages in this thread yet.',
            'composer' => $section === 'history' ? null : [
                'kind' => 'conversation',
                'section' => $section,
                'conversation' => $conversation,
                'display_phone' => $displayPhone,
                'display_name' => $composerContext['display_name'] ?? $identity['name'] ?? null,
                'lead' => $composerContext['lead'],
                'customer' => $composerContext['customer'],
                'repair_order' => $composerContext['repair_order'],
                'open_repair_orders' => $composerContext['open_repair_orders'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function leadThread(int $leadId): ?array
    {
        $lead = Lead::query()->find($leadId);

        if ($lead === null) {
            return null;
        }

        return [
            'title' => filled($lead->contact_name) ? (string) $lead->contact_name : 'Lead',
            'subtitle' => $lead->source->label(),
            'status_label' => $lead->state->label(),
            'assignment_label' => filled($lead->assigned_user_id) ? 'Assigned' : 'Unassigned',
            'events' => filled($lead->concern) ? [[
                'direction' => 'inbound',
                'direction_label' => 'Inbound',
                'body' => (string) $lead->concern,
                'channel_label' => $lead->source->label(),
                'occurred_at_label' => $lead->created_at
                    ?->timezone(config('app.display_timezone'))
                    ->format('M j, g:i A') ?? '',
            ]] : [],
            'empty_label' => 'No concern recorded on this lead.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callThread(int $callSessionId, string $section): ?array
    {
        $session = CallSession::query()->with(['customer', 'owner:id,name'])->find($callSessionId);

        if ($session === null) {
            return null;
        }

        $presented = $this->presentCallSession($session);
        $events = array_reverse($this->timeline->forCallSession($session, 50)->all());
        $identity = $this->identityProjection->forCallSession($session);

        return [
            'title' => $identity['name'] ?? $presented['display_phone'],
            'subtitle' => $presented['direction_label'],
            'status_label' => $presented['status_label'],
            'assignment_label' => filled($session->owned_by_user_id)
                ? ($session->owner?->name ?? 'Owned')
                : 'Unassigned',
            'identity' => $identity,
            'events' => $events,
            'empty_label' => 'No call detail recorded.',
            'composer' => $section === 'history' ? null : [
                'kind' => 'call',
                'section' => $section,
                'call_session_id' => $session->id,
            ],
        ];
    }

    /**
     * @return array{
     *     display_phone: string,
     *     headline: string,
     *     status_label: string,
     *     direction_label: string,
     * }
     */
    private function presentCallSession(CallSession $session): array
    {
        $session->loadMissing(['customer', 'owner:id,name']);

        $normalizedPhone = $this->callerDisplayPhone->normalizedForSession($session);
        $context = $this->callContextForSession($session, $normalizedPhone);
        $base = $this->incomingCallContextPresenter->present($session, $context);

        return [
            'display_phone' => (string) ($base['display_phone'] ?? 'Unknown caller'),
            'headline' => ($base['matched'] ?? false)
                ? (string) ($base['customer_name'] ?? 'Unknown')
                : (string) ($base['display_phone'] ?? 'Unknown caller'),
            'status_label' => (string) ($base['status_label'] ?? $session->status->operationalLabel()),
            'direction_label' => $session->direction->queueLabel(),
        ];
    }

    private function callContextForSession(CallSession $session, ?string $normalizedPhone): ?CustomerCallContext
    {
        if ($session->customer_id !== null) {
            return $this->callContextResolver->resolveForCustomer($session->customer);
        }

        if ($normalizedPhone === null || $normalizedPhone === '') {
            return null;
        }

        return $this->callContextResolver->resolve($normalizedPhone);
    }

    /**
     * @return array{customer_id: int|null, normalized_phone: string|null}
     */
    private function callListIdentity(CallSession $session): array
    {
        $normalizedPhone = $this->callerDisplayPhone->normalizedForSession($session);
        $context = $this->callContextForSession($session, $normalizedPhone);

        return [
            'customer_id' => $session->customer_id ?? $context?->customer?->id,
            'normalized_phone' => $normalizedPhone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function internalThread(InternalChannel $channel): array
    {
        $events = InternalMessage::query()
            ->where('internal_channel_id', $channel->id)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->map(fn (InternalMessage $message): array => [
                'direction' => 'internal',
                'direction_label' => $message->user?->name ?? 'Staff',
                'body' => (string) $message->body,
                'channel_label' => 'Internal',
                'occurred_at_label' => $message->created_at
                    ?->timezone(config('app.display_timezone'))
                    ->format('M j, g:i A') ?? '',
            ])
            ->all();

        return [
            'title' => $channel->name,
            'subtitle' => $channel->description ?? 'Internal coordination',
            'status_label' => 'Internal',
            'assignment_label' => count($events).' messages',
            'events' => $events,
            'empty_label' => 'No internal messages yet — messaging arrives in a later phase.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function internalContext(InternalChannel $channel): array
    {
        $memberCount = $channel->members()->count();

        return [
            'link_status' => 'Internal only',
            'fields' => array_filter([
                'Channel' => $channel->name,
                'Purpose' => $channel->description,
                'Members' => $memberCount > 0 ? (string) $memberCount : 'Default shop access',
                'Visibility' => $channel->is_private ? 'Private' : 'Shop',
            ]),
            'quick_actions' => [
                'View members',
                'Pinned notes',
                'Linked RO references',
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $listItems
     * @return list<array<string, mixed>>
     */
    private function withListPreviews(array $listItems): array
    {
        return array_map(function (array $item): array {
            $item['preview'] = $this->listPreviewLine($item);

            return $item;
        }, $listItems);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function listPreviewLine(array $item): string
    {
        $segments = [];

        $snippet = trim((string) ($item['snippet'] ?? ''));

        if ($snippet !== '') {
            $segments[] = $snippet;
        } elseif (filled($item['subtitle'] ?? null)) {
            $subtitle = trim((string) $item['subtitle']);
            $headline = trim((string) ($item['headline'] ?? $item['name'] ?? ''));

            if ($subtitle !== '' && $subtitle !== $headline) {
                $segments[] = $subtitle;
            }
        }

        if (filled($item['attention_reasons'] ?? null)) {
            $attentionReason = trim((string) ($item['attention_reasons'][0] ?? ''));

            if ($attentionReason !== '') {
                $segments[] = $attentionReason;
            }
        } elseif (filled($item['reason'] ?? null)) {
            $reason = trim((string) $item['reason']);

            if ($reason !== '' && ! in_array($reason, $segments, true)) {
                $segments[] = $reason;
            }
        }

        $assigned = trim((string) ($item['assigned_label'] ?? ''));

        if ($assigned !== '' && ! in_array(strtolower($assigned), ['unassigned', 'unowned', 'assigned'], true)) {
            $segments[] = $assigned;
        }

        return implode(' · ', array_values(array_unique(array_filter($segments))));
    }

    /**
     * @param  array<string, mixed>|null  $selected
     * @param  array<string, mixed>|null  $context
     * @param  array<string, mixed>|null  $thread
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    private function enrichSelectedAttention(
        ?array $selected,
        ?User $viewer,
        ?array $context,
        ?array $thread,
    ): array {
        if ($selected === null) {
            return [$context, $thread];
        }

        $context = is_array($context) ? $context : [];

        if (($selected['kind'] ?? '') === 'conversation') {
            $conversationId = (int) Str::after((string) ($selected['key'] ?? ''), 'conversation:');
            $candidate = $this->attentionCandidateBuilder->forConversationId($conversationId);

            if ($candidate !== null) {
                $context['attention'] = [
                    'pressure_score' => $candidate->pressureScore,
                    'reasons' => $candidate->reasons,
                ];
            }
        }

        $nudge = $this->nudges->forSelection($selected, $viewer);

        if ($nudge !== null) {
            $context['nudge'] = $nudge;

            if (is_array($thread)) {
                $composer = $thread['composer'] ?? null;

                if (is_array($composer) && in_array($composer['kind'] ?? '', ['conversation', 'call'], true)) {
                    $thread['composer']['nudge_key'] = $nudge['key'] ?? null;
                    $thread['composer']['entity_key'] = $nudge['entity_key'] ?? null;

                    if (filled($nudge['draft_reply'] ?? null)) {
                        $thread['composer']['draft_reply'] = $nudge['draft_reply'];
                    }
                }
            }
        }

        $insight = $this->analysisInsight->forSelection($selected);

        if ($this->analysisInsight->shouldShowAlongsideNudge($nudge ?? null, $insight)) {
            $insight['entity_key'] = (string) ($selected['key'] ?? '');
            $context['analysis_insight'] = $insight;
        }

        return [$context, $thread];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function placeholderPressureScore(array $row, int $index): int
    {
        $score = max(35, 96 - ($index * 7));

        if (($row['kind'] ?? '') === 'call' && ($row['status'] ?? '') === CallSessionStatus::Ringing->value) {
            $score += 8;
        }

        if (! ($row['matched'] ?? true)) {
            $score += 5;
        }

        if (($row['state'] ?? '') === 'unread' || ($row['state'] ?? '') === 'shop_turn') {
            $score += 4;
        }

        if (($row['kind'] ?? '') === 'website_lead') {
            $score += 6;
        }

        return min(100, $score);
    }

    /**
     * @return array<string, int|string>
     */
    private function selectionQuery(string $key, ?string $turn = null, ?string $filter = null): array
    {
        [$kind, $id] = explode(':', $key, 2);

        $params = match ($kind) {
            'conversation' => ['conversation' => (int) $id],
            'lead' => ['lead' => (int) $id],
            'call' => ['call' => (int) $id],
            default => [],
        };

        $resolvedFilter = $filter ?? match ($turn) {
            'shop' => 'needs',
            'customer' => 'waiting',
            default => request()->string('filter')->toString() ?: null,
        };

        if ($resolvedFilter !== null && $resolvedFilter !== '') {
            $params['filter'] = $this->normalizeListFilter($resolvedFilter, $turn);
        }

        return $params;
    }

    private function sectionRoute(string $section): string
    {
        return match ($section) {
            'history' => 'operations.communications.history',
            'attention' => 'operations.communications.inbox',
            default => 'operations.communications.inbox',
        };
    }

    private function selectionUrl(string $section, string $key): string
    {
        $filter = $section === 'inbox'
            ? (request()->string('filter')->toString()
                ?: match (request()->string('turn')->toString()) {
                    'customer' => 'waiting',
                    'shop' => 'needs',
                    default => 'needs',
                })
            : ($section === 'attention' ? 'needs' : null);

        $params = $this->selectionQuery($key, filter: $filter);

        if ($section === 'history') {
            $params = array_merge(request()->only(['q', 'from', 'to', 'media', 'page']), $params);
        }

        return route($this->sectionRoute($section), $params);
    }

    private function normalizeListFilter(?string $listFilter, ?string $turnFilter): string
    {
        if (in_array($listFilter, ['all', 'needs', 'waiting', 'resolved'], true)) {
            return $listFilter;
        }

        return match ($turnFilter) {
            'customer' => 'waiting',
            'shop' => 'needs',
            default => 'needs',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $listItems
     * @return list<array<string, mixed>>
     */
    private function enrichListIdentities(array $listItems): array
    {
        $phones = [];

        foreach ($listItems as $item) {
            $phones[] = (string) ($item['normalized_phone'] ?? $item['phone'] ?? '');
        }

        $contexts = $this->callContextResolver->mapForAttentionList($phones);

        return array_map(function (array $item) use ($contexts): array {
            $normalized = PhoneNumber::normalize((string) ($item['normalized_phone'] ?? $item['phone'] ?? ''));
            $context = $normalized !== null ? ($contexts[$normalized] ?? null) : null;
            $identity = $this->identityProjection->forListRow($item, $context);

            $item['headline'] = (string) ($identity['name'] ?? $item['headline'] ?? 'Unknown contact');
            $item['phone'] = $identity['phone'] ?? $item['phone'] ?? null;
            $item['subtitle'] = $identity['phone'] ?? $item['subtitle'] ?? '';
            $item['email'] = $identity['email'] ?? null;
            $item['known_customer'] = (bool) ($identity['known_customer'] ?? false);
            $item['link_status'] = (string) ($identity['link_status'] ?? '');
            $item['vehicle_label'] = $identity['vehicle_label'] ?? $item['vehicle_label'] ?? null;
            $item['ro_label'] = $identity['ro_label'] ?? $item['ro_label'] ?? null;
            $item['ro_status'] = $identity['ro_status'] ?? $item['ro_status'] ?? null;
            $item['shop_hint'] = trim(implode(' · ', array_filter([
                (string) ($item['vehicle_label'] ?? ''),
                (string) (($item['ro_label'] ?? '').(filled($item['ro_status'] ?? null) ? ' · '.$item['ro_status'] : '')),
            ]))) ?: ($item['shop_hint'] ?? null);
            $item['customer_id'] = $identity['customer_id'] ?? $item['customer_id'] ?? null;
            $item['normalized_phone'] = $identity['normalized_phone'] ?? $item['normalized_phone'] ?? null;

            return $item;
        }, $listItems);
    }

    /**
     * @return array{all: int, needs: int, waiting: int, resolved: int}
     */
    private function cheapFilterCounts(?User $viewer, ?Carbon $previousLastSeenAt): array
    {
        $turnCounts = $this->cheapTurnCounts();
        $needs = $turnCounts['shop'];

        if ($viewer !== null) {
            $queue = $this->queueResolver->resolveAttention($viewer, $previousLastSeenAt);
            $rows = is_array($queue['needs_attention'] ?? null) ? $queue['needs_attention'] : [];
            $needs = count($this->attentionDedupe->dedupe($rows));
        }

        $resolved = SchemaPresence::hasTable('conversations')
            ? (int) Conversation::query()->where('status', ConversationStatus::Resolved->value)->count()
            : 0;

        return [
            'all' => $needs + $turnCounts['customer'],
            'needs' => $needs,
            'waiting' => $turnCounts['customer'],
            'resolved' => $resolved,
        ];
    }

    private function conversationKeyForCallSession(int $callSessionId): ?string
    {
        $session = CallSession::query()->find($callSessionId);

        if ($session === null) {
            return null;
        }

        // A live call is an interrupt, not history — keep the call selection
        // so the advisor sees ringing/active state instead of the quiet
        // conversation shell.
        if (in_array($session->status, [CallSessionStatus::Ringing, CallSessionStatus::Answered], true)) {
            return null;
        }

        $phone = $this->callerDisplayPhone->normalizedForSession($session)
            ?? $session->normalized_from
            ?? PhoneNumber::normalize((string) $session->from_number);

        if ($phone === null || $phone === '') {
            return null;
        }

        $conversationId = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->where('contact_address', $phone)
            ->where('status', ConversationStatus::Open->value)
            ->value('id');

        return $conversationId !== null ? 'conversation:'.$conversationId : null;
    }

    /**
     * @param  list<array<string, mixed>>  $listItems
     * @param  array<string, mixed>  $callItem
     * @return array<string, mixed>|null
     */
    private function matchingConversationListItem(array $listItems, array $callItem): ?array
    {
        $customerByPhone = [];

        foreach (array_merge([$callItem], $listItems) as $item) {
            $normalized = PhoneNumber::normalize((string) ($item['normalized_phone'] ?? ''));

            if ($normalized !== null && isset($item['customer_id']) && (int) $item['customer_id'] > 0) {
                $customerByPhone[$normalized] = (int) $item['customer_id'];
            }
        }

        $callIdentity = $this->listDedupe->identityKey($callItem, $customerByPhone);

        foreach ($listItems as $item) {
            if (($item['kind'] ?? '') !== 'conversation') {
                continue;
            }

            if ($this->listDedupe->identityKey($item, $customerByPhone) === $callIdentity) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $listItems
     * @return array{shop: int, customer: int, all: int}
     */
    private function turnCounts(array $listItems): array
    {
        $shop = 0;
        $customer = 0;

        foreach ($listItems as $item) {
            if (($item['turn'] ?? '') === 'shop') {
                $shop++;
            } elseif (($item['turn'] ?? '') === 'customer') {
                $customer++;
            }
        }

        return [
            'shop' => $shop,
            'customer' => $customer,
            'all' => count($listItems),
        ];
    }

    /**
     * Lightweight nav badges — COUNT only, never full list presenters.
     *
     * @return array{shop: int, customer: int, all: int}
     */
    private function cheapTurnCounts(): array
    {
        if (! SchemaPresence::hasTable('conversations')) {
            return ['shop' => 0, 'customer' => 0, 'all' => 0];
        }

        $rows = Conversation::query()
            ->where('status', ConversationStatus::Open->value)
            ->selectRaw('waiting_on, COUNT(*) as aggregate')
            ->groupBy('waiting_on')
            ->pluck('aggregate', 'waiting_on');

        $shop = (int) ($rows[ConversationWaitingOn::Shop->value] ?? $rows['shop'] ?? 0);
        $customer = (int) ($rows[ConversationWaitingOn::Customer->value] ?? $rows['customer'] ?? 0);

        return [
            'shop' => $shop,
            'customer' => $customer,
            'all' => $shop + $customer,
        ];
    }

    /**
     * @return array{
     *     section: string,
     *     list_items: list<array<string, mixed>>,
     *     list_count: int,
     *     selected: array<string, mixed>|null,
     *     thread: array<string, mixed>|null,
     *     context: array<string, mixed>|null,
     *     turn_filter: null,
     *     turn_counts: array{shop: int, customer: int, all: int},
     * }
     */
    private function emptySection(string $section): array
    {
        return [
            'section' => $section,
            'list_items' => [],
            'list_count' => 0,
            'selected' => null,
            'thread' => null,
            'context' => null,
            'list_filter' => 'needs',
            'filter_counts' => ['all' => 0, 'needs' => 0, 'waiting' => 0, 'resolved' => 0],
            'turn_filter' => null,
            'turn_counts' => ['shop' => 0, 'customer' => 0, 'all' => 0],
        ];
    }
}
