<?php

namespace App\Ark\Growth\Integrations;

use App\Ark\Growth\Integrations\Contracts\SearchConsoleAdapter;
use App\Ark\Growth\Models\GrowthLandingPageMetric;
use App\Ark\Growth\Models\GrowthSearchQuery;
use Illuminate\Support\Carbon;

/**
 * Ingests Search Console rows as immutable daily snapshots — never overwrites prior days.
 */
final class SearchConsoleIngestService
{
    public function __construct(
        private readonly SearchConsoleAdapter $adapter,
    ) {}

    public function ingestDay(Carbon $reportDate): int
    {
        if (! $this->adapter->isConfigured()) {
            return 0;
        }

        $dateString = $reportDate->toDateString();
        $rows = 0;

        foreach ($this->adapter->fetchQueries($dateString, $dateString) as $row) {
            GrowthSearchQuery::query()->updateOrCreate(
                [
                    'query' => (string) $row['query'],
                    'report_date' => $dateString,
                ],
                [
                    'period_start' => $dateString,
                    'period_end' => $dateString,
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => isset($row['position']) ? (float) $row['position'] : null,
                    'metadata' => (array) ($row['metadata'] ?? []),
                ],
            );
            $rows++;
        }

        foreach ($this->adapter->fetchLandingPages($dateString, $dateString) as $row) {
            $path = '/'.trim((string) ($row['path'] ?? ''), '/');
            if ($path === '/') {
                $path = '/';
            }

            GrowthLandingPageMetric::query()->updateOrCreate(
                [
                    'path' => $path,
                    'report_date' => $dateString,
                ],
                [
                    'period_start' => $dateString,
                    'period_end' => $dateString,
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => isset($row['position']) ? (float) $row['position'] : null,
                    'metadata' => (array) ($row['metadata'] ?? []),
                ],
            );
            $rows++;
        }

        return $rows;
    }
}
