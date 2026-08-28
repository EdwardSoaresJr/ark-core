<?php

namespace App\Ark\Growth\Integrations;

use App\Ark\Growth\Integrations\Contracts\BusinessProfileAdapter;
use App\Ark\Growth\Models\GrowthLocationMetric;
use Illuminate\Support\Carbon;

/**
 * Ingests Google Business Profile daily metrics as immutable snapshots.
 */
final class BusinessProfileIngestService
{
    public function __construct(
        private readonly BusinessProfileAdapter $adapter,
    ) {}

    public function ingestDay(Carbon $reportDate): int
    {
        return $this->ingestBetween($reportDate, $reportDate)['rows'];
    }

    /**
     * @return array{days: int, rows: int, start_date: string, end_date: string}
     */
    public function ingestBetween(Carbon $startDate, Carbon $endDate): array
    {
        if (! $this->adapter->isConfigured()) {
            return [
                'days' => 0,
                'rows' => 0,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ];
        }

        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $rows = 0;
        $days = [];

        foreach ($this->adapter->fetchMetricsBetween($start->toDateString(), $end->toDateString()) as $row) {
            $reportDate = (string) ($row['report_date'] ?? '');

            if ($reportDate === '') {
                continue;
            }

            GrowthLocationMetric::query()->updateOrCreate(
                [
                    'metric' => (string) $row['metric'],
                    'report_date' => $reportDate,
                ],
                [
                    'value' => (int) ($row['value'] ?? 0),
                    'metadata' => (array) ($row['metadata'] ?? []),
                ],
            );

            $days[$reportDate] = true;
            $rows++;
        }

        return [
            'days' => count($days),
            'rows' => $rows,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }
}
