<?php

namespace App\Ark\Operations\Communications;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Left-rail Communications badge — deduped attention count (one customer, one badge).
 */
final class CommunicationsNavPressure
{
    public function __construct(
        private readonly CommunicationsQueueResolver $queueResolver,
    ) {}

    /**
     * @return array{
     *     nav_pressure_count: int,
     *     attention_count: int,
     *     workboard_actionable: int,
     *     call_count: int,
     *     summary: array<string, mixed>,
     *     workboard_counts: array<string, int>,
     * }
     */
    public function resolve(?User $viewer, ?Carbon $previousLastSeenAt = null): array
    {
        if ($viewer === null) {
            return $this->empty();
        }

        // Share the same attention request cache as the channel strip / Needs You list.
        $attention = $this->queueResolver->resolveAttention($viewer, $previousLastSeenAt);
        $commsPressure = app(AdvisorCommsPressure::class)->resolve($viewer, $previousLastSeenAt);
        $workboardCounts = [
            'calls_waiting' => (int) ($commsPressure['summary']['call_count'] ?? 0),
            'needs_shop' => (int) ($commsPressure['summary']['needs_shop_count'] ?? 0),
            'new_opportunities' => (int) ($commsPressure['summary']['new_lead_count'] ?? 0),
            'total_actionable' => (int) ($commsPressure['summary']['workboard_actionable'] ?? 0),
        ];
        $summary = is_array($attention['summary'] ?? null) ? $attention['summary'] : [];

        $attentionCount = (int) ($attention['count'] ?? 0);

        return [
            'nav_pressure_count' => $attentionCount,
            'attention_count' => $attentionCount,
            'workboard_actionable' => (int) ($commsPressure['summary']['workboard_actionable'] ?? 0),
            'call_count' => (int) ($commsPressure['summary']['call_count'] ?? 0),
            'summary' => $summary,
            'workboard_counts' => $workboardCounts,
        ];
    }

    /**
     * @return array{
     *     nav_pressure_count: int,
     *     attention_count: int,
     *     workboard_actionable: int,
     *     call_count: int,
     *     summary: array<string, mixed>,
     *     workboard_counts: array<string, int>,
     * }
     */
    private function empty(): array
    {
        return [
            'nav_pressure_count' => 0,
            'attention_count' => 0,
            'workboard_actionable' => 0,
            'call_count' => 0,
            'summary' => [],
            'workboard_counts' => [],
        ];
    }
}
