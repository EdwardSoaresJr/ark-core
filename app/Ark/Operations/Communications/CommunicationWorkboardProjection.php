<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationStatus;
use App\Ark\Operations\Conversations\ConversationWaitingOn;
use App\Ark\Operations\Leads\IngressCreateContactUrl;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadConfirmationAuditConversation;
use App\Ark\Operations\Leads\LeadPressure;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Runtime\Database\SchemaPresence;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Morning triage projection — composes calls, leads, and conversation posture lanes.
 *
 * Not message authority. Conversation and Lead remain authoritative stores.
 */
final class CommunicationWorkboardProjection
{
    private const LAYOUT_COUNTS_CACHE_KEY = 'comms_workboard_layout_counts';

    public function __construct(
        private readonly ConversationWorkboardPresenter $conversationPresenter,
        private readonly LeadPressure $leadPressure,
        private readonly CommunicationsQueueResolver $queueResolver,
        private readonly LeadConfirmationAuditConversation $confirmationAudit,
    ) {}

    /**
     * Layout pressure only — counts without loading lead/conversation rows.
     *
     * @return array{
     *     calls_waiting: int,
     *     new_opportunities: int,
     *     needs_shop: int,
     *     total_actionable: int,
     *     lead_pressure_open: int,
     *     sms_open_leads: int,
     * }
     */
    public function resolveLayoutCounts(?User $viewer, ?Carbon $previousLastSeenAt = null): array
    {
        if ($viewer === null || ! SchemaPresence::hasTable('leads')) {
            return $this->emptyLayoutCounts();
        }

        $request = request();
        $cacheKey = self::LAYOUT_COUNTS_CACHE_KEY.':'.($previousLastSeenAt?->timestamp ?? 'null');

        if ($request !== null && $request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        // Share Attention cache with ops layout — never hydrate needsShop() row presenters for counts.
        $queue = $this->queueResolver->resolveAttention($viewer, $previousLastSeenAt);
        $queueSummary = is_array($queue['summary'] ?? null) ? $queue['summary'] : [];
        $callCount = (int) ($queueSummary['call_count'] ?? count(is_array($queue['calls'] ?? null) ? $queue['calls'] : []));

        $newOpportunitiesCount = Lead::query()
            ->open()
            ->notSpam()
            ->where('state', '!=', LeadState::WaitingCustomer->value)
            ->count();

        $needsShopCount = SchemaPresence::hasColumn('conversations', 'waiting_on')
            ? (int) Conversation::query()
                ->openPosture()
                ->where('waiting_on', ConversationWaitingOn::Shop->value)
                ->count()
            : 0;

        $leadPressure = $this->leadPressure->resolveOpenCount($viewer);
        $smsOpenLeads = Lead::query()->open()->where('source', LeadSource::Sms)->count();

        $counts = [
            'calls_waiting' => $callCount,
            'new_opportunities' => $newOpportunitiesCount,
            'needs_shop' => $needsShopCount,
            'total_actionable' => $callCount + $newOpportunitiesCount + $needsShopCount,
            'lead_pressure_open' => (int) ($leadPressure['open_count'] ?? 0),
            'sms_open_leads' => $smsOpenLeads,
        ];

        $request?->attributes->set($cacheKey, $counts);

        return $counts;
    }

    /**
     * @return array{
     *     calls_waiting: list<array<string, mixed>>,
     *     new_opportunities: list<array<string, mixed>>,
     *     needs_shop: list<array<string, mixed>>,
     *     waiting_customer: list<array<string, mixed>>,
     *     recently_resolved: list<array<string, mixed>>,
     *     since_last_shift: list<array<string, mixed>>,
     *     since_last_shift_boundary_label: string,
     *     needs_attention_now: list<array<string, mixed>>,
     *     unknown: list<array<string, mixed>>,
     *     recent_activity: list<array<string, mixed>>,
     *     counts: array<string, int>,
     *     lead_pressure: array<string, mixed>,
     * }
     */
    public function resolve(
        ?User $viewer,
        ?Carbon $previousLastSeenAt = null,
        bool $includeRecovery = false,
    ): array {
        if ($viewer === null || ! SchemaPresence::hasTable('leads')) {
            return $this->empty();
        }

        $cacheKey = sprintf(
            'comms_workboard:%s:%s:%d',
            $viewer->id,
            $previousLastSeenAt?->timestamp ?? 'null',
            $includeRecovery ? 1 : 0,
        );
        $request = request();

        if ($request !== null && $request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        $queue = $includeRecovery
            ? $this->queueResolver->resolve($viewer, $previousLastSeenAt)
            : $this->queueResolver->resolveAttention($viewer, $previousLastSeenAt);

        $callsWaiting = is_array($queue['calls'] ?? null) ? $queue['calls'] : [];
        $sinceLastShift = $includeRecovery && is_array($queue['since_last_shift'] ?? null)
            ? $queue['since_last_shift']
            : [];
        $sinceLastShiftBoundaryLabel = $includeRecovery
            ? (string) ($queue['since_last_shift_boundary_label'] ?? '')
            : '';
        $needsAttention = is_array($queue['needs_attention'] ?? null) ? $queue['needs_attention'] : [];
        $needsAttentionNow = array_values(array_filter(
            $needsAttention,
            fn (array $row): bool => (bool) ($row['matched'] ?? false),
        ));
        $queueUnknown = is_array($queue['unknown'] ?? null) ? $queue['unknown'] : [];
        $recentActivity = $includeRecovery && is_array($queue['recent_activity'] ?? null)
            ? $queue['recent_activity']
            : [];

        $newOpportunities = $this->newOpportunities();
        $needsShop = $this->needsShop();
        $waitingCustomer = $this->waitingCustomer();
        $recentlyResolved = $this->recentlyResolvedConversations();
        $recentlyResolvedTotal = $this->recentlyResolvedConversationsTotal();
        $leadPressure = $this->leadPressure->resolve($viewer);
        $smsOpenLeads = Lead::query()->open()->where('source', LeadSource::Sms)->count();

        $resolved = [
            'calls_waiting' => $callsWaiting,
            'new_opportunities' => $newOpportunities,
            'needs_shop' => $needsShop,
            'waiting_customer' => $waitingCustomer,
            'recently_resolved' => $recentlyResolved,
            'since_last_shift' => $sinceLastShift,
            'since_last_shift_boundary_label' => $sinceLastShiftBoundaryLabel,
            'needs_attention_now' => $needsAttentionNow,
            'unknown' => $queueUnknown,
            'recent_activity' => $recentActivity,
            'counts' => [
                'calls_waiting' => count($callsWaiting),
                'new_opportunities' => count($newOpportunities),
                'needs_shop' => count($needsShop),
                'waiting_customer' => count($waitingCustomer),
                'recently_resolved' => count($recentlyResolved),
                'recently_resolved_total' => $recentlyResolvedTotal,
                'since_last_shift' => count($sinceLastShift),
                'total_actionable' => count($callsWaiting)
                    + count($newOpportunities)
                    + count($needsShop),
                'sms_open_leads' => $smsOpenLeads,
                'lead_pressure_open' => (int) ($leadPressure['open_count'] ?? 0),
            ],
            'lead_pressure' => $leadPressure,
        ];

        $request?->attributes->set($cacheKey, $resolved);

        return $resolved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newOpportunities(): array
    {
        return Lead::query()
            ->open()
            ->notSpam()
            ->where('state', '!=', LeadState::WaitingCustomer->value)
            ->with(['conversation.messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1), 'customer', 'repairOrder'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Lead $lead): array => $this->presentLeadOpportunity($lead))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLeadOpportunity(Lead $lead): array
    {
        $latestMessage = $lead->conversation?->messages->first();
        $snippet = $latestMessage !== null
            ? Str::limit(trim((string) $latestMessage->body), 140)
            : Str::limit($lead->concern, 140);

        $replyUrl = $lead->conversation_id !== null
            ? route('operations.conversations.reply', $lead->conversation_id)
            : null;

        return [
            'kind' => 'lead',
            'lead_id' => $lead->id,
            'source' => $lead->source->value,
            'source_label' => $lead->source->label(),
            'age_label' => $lead->ageLabel(),
            'concern' => $lead->concern,
            'contact_name' => $lead->contact_name ?: 'Unknown',
            'display_phone' => $lead->display_phone,
            'state' => $lead->state->value,
            'state_label' => $lead->state->label(),
            'snippet' => $snippet,
            'conversation_id' => $lead->conversation_id,
            'customer_id' => $lead->customer_id,
            'customer_url' => $lead->customer_id !== null
                ? route('operations.customers.show', $lead->customer_id)
                : null,
            'repair_order_id' => $lead->repair_order_id,
            'repair_order_url' => $lead->repair_order_id !== null
                ? route('operations.repair-orders.show', $lead->repair_order_id)
                : null,
            'reply_url' => $replyUrl,
            'intake_url' => route('operations.leads.intake', $lead),
            'create_contact_url' => IngressCreateContactUrl::forLead($lead),
            'leads_url' => \App\Ark\Operations\Communications\CommunicationsNeedsYou::url(),
            'queue_tab' => CommunicationsSurfaceChannel::Sms->value,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function needsShop(): array
    {
        if (! SchemaPresence::hasColumn('conversations', 'waiting_on')) {
            return [];
        }

        return Conversation::query()
            ->openPosture()
            ->where('waiting_on', ConversationWaitingOn::Shop->value)
            ->with(['messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1)])
            ->orderBy('posture_changed_at')
            ->get()
            ->reject(fn (Conversation $conversation): bool => $this->confirmationAudit->suppressFromShopTurn($conversation))
            ->map(fn (Conversation $conversation): array => $this->conversationPresenter->present($conversation, 'needs_shop'))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function waitingCustomer(): array
    {
        $conversationRows = [];

        if (SchemaPresence::hasColumn('conversations', 'waiting_on')) {
            $conversationRows = Conversation::query()
                ->openPosture()
                ->where('waiting_on', ConversationWaitingOn::Customer->value)
                ->with(['messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1)])
                ->orderByDesc('posture_changed_at')
                ->get()
                ->map(fn (Conversation $conversation): array => $this->conversationPresenter->present($conversation, 'waiting_customer'))
                ->all();
        }

        $leadRows = Lead::query()
            ->open()
            ->notSpam()
            ->where('state', LeadState::WaitingCustomer->value)
            ->with(['conversation.messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1), 'customer', 'repairOrder'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Lead $lead): array {
                $row = $this->presentLeadOpportunity($lead);
                $row['kind'] = 'waiting_customer_lead';

                return $row;
            })
            ->all();

        return collect([...$conversationRows, ...$leadRows])
            ->sortByDesc(fn (array $row): int => strtotime((string) ($row['occurred_at'] ?? '')) ?: 0)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentlyResolvedConversations(): array
    {
        if (! SchemaPresence::hasColumn('conversations', 'resolved_at')) {
            return [];
        }

        $since = now()->subDays(7);

        return Conversation::query()
            ->where('status', ConversationStatus::Resolved->value)
            ->where('resolved_at', '>=', $since)
            ->with(['messages' => fn ($query) => $query->orderByDesc('occurred_at')->limit(1)])
            ->orderByDesc('resolved_at')
            ->limit(10)
            ->get()
            ->map(fn (Conversation $conversation): array => $this->conversationPresenter->present($conversation, 'recently_resolved'))
            ->all();
    }

    private function recentlyResolvedConversationsTotal(): int
    {
        if (! SchemaPresence::hasColumn('conversations', 'resolved_at')) {
            return 0;
        }

        return Conversation::query()
            ->where('status', ConversationStatus::Resolved->value)
            ->where('resolved_at', '>=', now()->subDays(7))
            ->count();
    }

    /**
     * @return array{
     *     calls_waiting: list<array<string, mixed>>,
     *     new_opportunities: list<array<string, mixed>>,
     *     needs_shop: list<array<string, mixed>>,
     *     waiting_customer: list<array<string, mixed>>,
     *     recently_resolved: list<array<string, mixed>>,
     *     since_last_shift: list<array<string, mixed>>,
     *     since_last_shift_boundary_label: string,
     *     needs_attention_now: list<array<string, mixed>>,
     *     unknown: list<array<string, mixed>>,
     *     recent_activity: list<array<string, mixed>>,
     *     counts: array<string, int>,
     *     lead_pressure: array<string, mixed>,
     * }
     */
    private function empty(): array
    {
        return [
            'calls_waiting' => [],
            'new_opportunities' => [],
            'needs_shop' => [],
            'waiting_customer' => [],
            'recently_resolved' => [],
            'since_last_shift' => [],
            'since_last_shift_boundary_label' => '',
            'needs_attention_now' => [],
            'unknown' => [],
            'recent_activity' => [],
            'counts' => array_merge($this->emptyLayoutCounts(), [
                'waiting_customer' => 0,
                'recently_resolved' => 0,
                'recently_resolved_total' => 0,
                'since_last_shift' => 0,
            ]),
            'lead_pressure' => $this->leadPressure->resolve(null),
        ];
    }

    /**
     * @return array{
     *     calls_waiting: int,
     *     new_opportunities: int,
     *     needs_shop: int,
     *     total_actionable: int,
     *     lead_pressure_open: int,
     *     sms_open_leads: int,
     * }
     */
    private function emptyLayoutCounts(): array
    {
        return [
            'calls_waiting' => 0,
            'new_opportunities' => 0,
            'needs_shop' => 0,
            'total_actionable' => 0,
            'lead_pressure_open' => 0,
            'sms_open_leads' => 0,
        ];
    }
}
