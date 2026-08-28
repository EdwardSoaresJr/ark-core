<?php

namespace App\Ark\Growth\Integrations;

use App\Ark\Growth\Integrations\Contracts\BusinessProfileAdapter;
use Illuminate\Support\Carbon;

/**
 * Deterministic Business Profile metrics for local dev and tests when Google credentials are absent.
 */
final class FixtureBusinessProfileAdapter implements BusinessProfileAdapter
{
    public function isConfigured(): bool
    {
        return (bool) config('growth.integrations.google_business_profile.fixture_enabled', true);
    }

    public function fetchDailyMetrics(string $reportDate): array
    {
        return $this->fetchMetricsBetween($reportDate, $reportDate);
    }

    public function fetchMetricsBetween(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $rows = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            foreach ($this->metricsForDate($date->toDateString()) as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{metric: string, value: int, report_date: string, metadata: array<string, mixed>}>
     */
    private function metricsForDate(string $reportDate): array
    {
        return collect(config('growth.business_profile.fixture.metrics', []))
            ->map(function (array $row) use ($reportDate): array {
                return [
                    'metric' => (string) $row['metric'],
                    'value' => (int) ($row['value'] ?? 0),
                    'report_date' => $reportDate,
                    'metadata' => [
                        'source' => 'fixture',
                        'report_date' => $reportDate,
                    ],
                ];
            })
            ->all();
    }
}
