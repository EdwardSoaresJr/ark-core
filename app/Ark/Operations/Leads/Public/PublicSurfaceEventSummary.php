<?php

namespace App\Ark\Operations\Leads\Public;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only funnel counts for observation — Bookend projection later.
 */
class PublicSurfaceEventSummary
{
    /**
     * @return array{
     *     visitors: int,
     *     lead_starts: int,
     *     lead_submissions: int,
     *     leads_created: int,
     *     call_clicks: int,
     *     text_clicks: int,
     *     abandoned_after_start: int,
     *     start_to_submit_rate: float|null,
     *     attribution: array<string, int>,
     * }
     */
    public function forPeriod(Carbon $from, Carbon $to): array
    {
        $base = PublicSurfaceEvent::query()
            ->whereBetween('occurred_at', [$from, $to]);

        $visitors = $this->distinctSessions((clone $base), PublicSurfaceEventType::SurfaceViewed);
        $leadStarts = $this->distinctSessions((clone $base), PublicSurfaceEventType::LeadStarted);
        $leadSubmissions = $this->distinctSessions((clone $base), PublicSurfaceEventType::LeadSubmitted);
        $leadsCreated = (clone $base)
            ->where('event', PublicSurfaceEventType::LeadCreated->value)
            ->count();
        $callClicks = (clone $base)
            ->where('event', PublicSurfaceEventType::CallClicked->value)
            ->count();
        $textClicks = (clone $base)
            ->where('event', PublicSurfaceEventType::TextClicked->value)
            ->count();

        $abandoned = max(0, $leadStarts - $leadSubmissions);

        return [
            'visitors' => $visitors,
            'lead_starts' => $leadStarts,
            'lead_submissions' => $leadSubmissions,
            'leads_created' => $leadsCreated,
            'call_clicks' => $callClicks,
            'text_clicks' => $textClicks,
            'abandoned_after_start' => $abandoned,
            'start_to_submit_rate' => $leadStarts > 0
                ? round(($leadSubmissions / $leadStarts) * 100, 1)
                : null,
            'attribution' => $this->attributionMix($from, $to),
        ];
    }

    private function distinctSessions($query, PublicSurfaceEventType $type): int
    {
        return (int) $query
            ->where('event', $type->value)
            ->distinct()
            ->count('session_id');
    }

    /**
     * @return array<string, int>
     */
    private function attributionMix(Carbon $from, Carbon $to): array
    {
        return PublicSurfaceEvent::query()
            ->select('attribution', DB::raw('count(distinct session_id) as visitors'))
            ->where('event', PublicSurfaceEventType::SurfaceViewed->value)
            ->whereBetween('occurred_at', [$from, $to])
            ->whereNotNull('attribution')
            ->groupBy('attribution')
            ->orderByDesc('visitors')
            ->pluck('visitors', 'attribution')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
