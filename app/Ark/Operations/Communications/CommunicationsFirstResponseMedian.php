<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Notebook query for sprint KPI — median minutes from website lead submit to first advisor outbound.
 */
final class CommunicationsFirstResponseMedian
{
    /**
     * @return array{
     *     sample_size: int,
     *     median_minutes: float|null,
     *     p90_minutes: float|null,
     *     rows: list<array{lead_id: int, submitted_at: string, first_contacted_at: string, minutes: float}>,
     * }
     */
    public function forWebsiteLeads(?Carbon $since = null, int $limit = 100): array
    {
        $since ??= now()->subDays(30);

        $leads = Lead::query()
            ->where('source', LeadSource::Website)
            ->whereNotNull('first_contacted_at')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'created_at', 'first_contacted_at']);

        $rows = $leads
            ->map(function (Lead $lead): array {
                $minutes = $lead->created_at->diffInSeconds($lead->first_contacted_at) / 60;

                return [
                    'lead_id' => $lead->id,
                    'submitted_at' => $lead->created_at->toIso8601String(),
                    'first_contacted_at' => $lead->first_contacted_at->toIso8601String(),
                    'minutes' => round($minutes, 1),
                ];
            })
            ->values();

        $minutes = $rows->pluck('minutes')->sort()->values();

        return [
            'sample_size' => $minutes->count(),
            'median_minutes' => $this->median($minutes),
            'p90_minutes' => $this->percentile($minutes, 90),
            'rows' => $rows->all(),
        ];
    }

    /**
     * @param  Collection<int, float>  $values
     */
    private function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $count = $values->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return round((float) $values[$middle], 1);
        }

        return round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 1);
    }

    /**
     * @param  Collection<int, float>  $values
     */
    private function percentile(Collection $values, int $percentile): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $index = (int) ceil(($percentile / 100) * $values->count()) - 1;
        $index = max(0, min($index, $values->count() - 1));

        return round((float) $values[$index], 1);
    }
}
