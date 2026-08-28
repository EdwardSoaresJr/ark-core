<?php

namespace App\Ark\Growth\Integrations;

use App\Ark\Growth\Integrations\Contracts\SearchConsoleAdapter;

/**
 * Deterministic Search Console rows for local dev and tests when Google credentials are absent.
 */
final class FixtureSearchConsoleAdapter implements SearchConsoleAdapter
{
    public function isConfigured(): bool
    {
        return (bool) config('growth.integrations.google_search_console.fixture_enabled', true);
    }

    public function fetchQueries(string $startDate, string $endDate): array
    {
        return collect(config('growth.search_console.fixture.queries', []))
            ->map(function (array $row) use ($startDate): array {
                $impressions = (int) ($row['impressions'] ?? 0);
                $clicks = (int) ($row['clicks'] ?? 0);

                return [
                    'query' => (string) $row['query'],
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0,
                    'position' => (float) ($row['position'] ?? 0),
                    'metadata' => [
                        'source' => 'fixture',
                        'report_date' => $startDate,
                    ],
                ];
            })
            ->all();
    }

    public function fetchLandingPages(string $startDate, string $endDate): array
    {
        return collect(config('growth.search_console.fixture.landing_pages', []))
            ->map(function (array $row) use ($startDate): array {
                $impressions = (int) ($row['impressions'] ?? 0);
                $clicks = (int) ($row['clicks'] ?? 0);

                return [
                    'path' => (string) ($row['path'] ?? '/'),
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0,
                    'position' => (float) ($row['position'] ?? 0),
                    'metadata' => [
                        'source' => 'fixture',
                        'report_date' => $startDate,
                    ],
                ];
            })
            ->all();
    }

    public function fetchIndexCoverage(): array
    {
        return [];
    }
}
