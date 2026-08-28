<?php

namespace App\Ark\Operations\Communications;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Authoritative "customer pressure" count for attention gate and comms bar.
 */
class AdvisorCommsPressure
{
    private const REQUEST_CACHE_PREFIX = 'advisor_comms_pressure:';

    public function __construct(
        private readonly CommunicationWorkboardProjection $workboardProjection,
        private readonly CommunicationsQueueResolver $queueResolver,
    ) {}

    public function hasUnresolvedPressure(?User $viewer): bool
    {
        return $this->unresolvedCount($viewer) > 0;
    }

    public function unresolvedCount(?User $viewer): int
    {
        if ($viewer === null) {
            return 0;
        }

        return (int) ($this->resolve($viewer)['count'] ?? 0);
    }

    /**
     * @return array{
     *     count: int,
     *     summary: array<string, mixed>,
     *     attention_url: string,
     *     has_live_calls: bool,
     * }
     */
    public function resolve(?User $viewer, ?Carbon $previousLastSeenAt = null): array
    {
        if ($viewer === null) {
            return [
                'count' => 0,
                'summary' => [],
                'attention_url' => '',
                'has_live_calls' => false,
            ];
        }

        $request = request();
        $cacheKey = self::REQUEST_CACHE_PREFIX.($previousLastSeenAt?->timestamp ?? 'null');

        if ($request !== null && $request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        $queue = $this->queueResolver->resolveAttention($viewer, $previousLastSeenAt);
        $queueSummary = is_array($queue['summary'] ?? null) ? $queue['summary'] : [];
        $actionableCount = (int) ($queueSummary['count'] ?? 0);
        $callCount = (int) ($queueSummary['call_count'] ?? 0);
        $hasLiveCalls = (bool) ($queueSummary['has_live_calls'] ?? false);

        $workboardCounts = $this->workboardProjection->resolveLayoutCounts($viewer, $previousLastSeenAt);
        $needsShopCount = (int) ($workboardCounts['needs_shop'] ?? 0);
        $newLeadCount = (int) ($workboardCounts['new_opportunities'] ?? 0);
        $workboardActionable = (int) ($workboardCounts['total_actionable'] ?? 0);

        $resolved = [
            'count' => $actionableCount,
            'summary' => [
                'count' => $actionableCount,
                'call_count' => $callCount,
                'needs_shop_count' => $needsShopCount,
                'new_lead_count' => $newLeadCount,
                'workboard_actionable' => $workboardActionable,
                'has_live_calls' => $hasLiveCalls,
                'breakdown_label' => (string) ($queueSummary['breakdown_label'] ?? ''),
                'trigger_label' => (string) ($queueSummary['trigger_label'] ?? ''),
                'urgency' => (string) ($queueSummary['urgency'] ?? ($hasLiveCalls ? 'live' : ($actionableCount > 0 ? 'attention' : 'idle'))),
            ],
            'attention_url' => CommunicationsNeedsYou::url(),
            'has_live_calls' => $hasLiveCalls,
        ];

        $request?->attributes->set($cacheKey, $resolved);

        return $resolved;
    }
}
