<?php

namespace App\Ark\Growth\Integrations;

use App\Ark\Growth\Integrations\Contracts\BusinessProfileAdapter;
use App\Ark\Growth\Maintenance\GrowthSyncTaskKey;
use App\Ark\Growth\Maintenance\GrowthSyncTaskRecorder;
use Illuminate\Support\Carbon;

final class GoogleBusinessProfileSyncService
{
    public function __construct(
        private readonly BusinessProfileAdapter $adapter,
        private readonly BusinessProfileIngestService $ingest,
        private readonly GrowthSyncTaskRecorder $recorder,
    ) {}

    public function sync(?Carbon $reportDate = null): void
    {
        $reportDate ??= now()->subDay()->startOfDay();

        $this->recorder->markRunning(GrowthSyncTaskKey::GoogleBusinessProfile);

        if (! $this->adapter->isConfigured()) {
            $this->recorder->recordSkipped(
                GrowthSyncTaskKey::GoogleBusinessProfile,
                'Google Business Profile integration is not configured for this shop.',
            );

            return;
        }

        try {
            $rows = $this->ingest->ingestDay($reportDate);

            if ($rows === 0) {
                $this->recorder->recordSkipped(
                    GrowthSyncTaskKey::GoogleBusinessProfile,
                    'Google Business Profile returned no metric rows for '.$reportDate->toDateString().'.',
                    ['report_date' => $reportDate->toDateString(), 'rows' => 0],
                );

                return;
            }

            $this->recorder->recordSuccess(
                GrowthSyncTaskKey::GoogleBusinessProfile,
                "Imported {$rows} Google Business Profile metric rows for {$reportDate->toDateString()}.",
                ['report_date' => $reportDate->toDateString(), 'rows' => $rows],
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->recorder->recordFailure(
                GrowthSyncTaskKey::GoogleBusinessProfile,
                $exception->getMessage(),
                ['report_date' => $reportDate->toDateString()],
            );
        }
    }

    /**
     * @return array{days: int, rows: int, start_date: string, end_date: string}|null
     */
    public function backfill(Carbon $startDate, Carbon $endDate): ?array
    {
        $this->recorder->markRunning(GrowthSyncTaskKey::GoogleBusinessProfile);

        if (! $this->adapter->isConfigured()) {
            $this->recorder->recordSkipped(
                GrowthSyncTaskKey::GoogleBusinessProfile,
                'Google Business Profile integration is not configured for this shop.',
            );

            return null;
        }

        try {
            $result = $this->ingest->ingestBetween($startDate, $endDate);

            if ($result['rows'] === 0) {
                $this->recorder->recordSkipped(
                    GrowthSyncTaskKey::GoogleBusinessProfile,
                    'Google Business Profile returned no metric rows for '.$result['start_date'].' through '.$result['end_date'].'.',
                    $result,
                );

                return $result;
            }

            $this->recorder->recordSuccess(
                GrowthSyncTaskKey::GoogleBusinessProfile,
                "Backfilled {$result['rows']} Google Business Profile metric rows across {$result['days']} days ({$result['start_date']} to {$result['end_date']}).",
                $result,
            );

            return $result;
        } catch (\Throwable $exception) {
            report($exception);

            $this->recorder->recordFailure(
                GrowthSyncTaskKey::GoogleBusinessProfile,
                $exception->getMessage(),
                [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
            );

            return null;
        }
    }
}
