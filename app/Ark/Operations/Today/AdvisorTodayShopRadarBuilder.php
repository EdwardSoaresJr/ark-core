<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Workboard\WorkboardQueueCatalog;
use App\Ark\Operations\Workboard\WorkboardTriageProjection;
use Illuminate\Support\Collection;

final class AdvisorTodayShopRadarBuilder
{
    /** @var list<string> */
    private const WORK_QUEUE_KEYS = [
        'customer_waiting',
        'waiting_approval',
        'needs_diagnosis',
        'waiting_parts',
        'ready_pickup',
    ];

    public function __construct(
        private readonly WorkboardTriageProjection $triageProjection,
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return list<TodayWorkQueueRow>
     */
    public function workQueueRows(Collection $repairOrders): array
    {
        $navCounts = $this->triageProjection->advisorNavCounts($repairOrders);

        return collect(self::WORK_QUEUE_KEYS)
            ->map(function (string $key) use ($repairOrders, $navCounts): TodayWorkQueueRow {
                $queueOrders = $this->triageProjection->repairOrdersInQueue($repairOrders, $key);
                $revenueCents = $queueOrders->sum(
                    fn (RepairOrder $repairOrder): int => $this->totalsCalculator->totalsFor($repairOrder)->totalCents(),
                );
                $oldestMinutes = $navCounts['oldest_age_by_queue'][$key] ?? 0;

                return new TodayWorkQueueRow(
                    key: $key,
                    label: WorkboardQueueCatalog::labelForQueue($key) ?? $key,
                    count: WorkboardQueueCatalog::queueCountFromNavCounts($navCounts, $key),
                    oldestAgeLabel: $oldestMinutes > 0 ? $this->ageLabel($oldestMinutes) : null,
                    revenueTrappedCents: $revenueCents,
                    revenueTrappedLabel: '$'.number_format($revenueCents / 100, 0),
                    workboardUrl: WorkboardQueueCatalog::queueUrl($key),
                );
            })
            ->all();
    }

    private function ageLabel(int $ageMinutes): string
    {
        if ($ageMinutes >= 7 * 24 * 60) {
            $days = (int) floor($ageMinutes / (24 * 60));

            return $days.'d';
        }

        if ($ageMinutes >= 24 * 60) {
            $days = (int) floor($ageMinutes / (24 * 60));

            return $days.'d';
        }

        if ($ageMinutes >= 60) {
            $hours = (int) floor($ageMinutes / 60);

            return $hours.'h';
        }

        return $ageMinutes.'m';
    }
}
