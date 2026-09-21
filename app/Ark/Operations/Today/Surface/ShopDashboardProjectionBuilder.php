<?php

namespace App\Ark\Operations\Today\Surface;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use Brick\Money\Money;

/**
 * Open-queue shop dashboard from disposition authority (GET-safe reads).
 *
 * Car Count = open operational ROs.
 * Pending / Declined / Approved = sum of recommended / declined / approved line totals.
 * ARO = Approved ÷ Car Count.
 * Close Ratio = Approved ÷ (Pending + Declined + Approved).
 *
 * Drill-downs land on Repair Orders inventory with open + status/disposition filters.
 */
final class ShopDashboardProjectionBuilder
{
        /**
         * Display order for status lanes (intake → production → pickup).
         *
         * @var list<string>
         */
    private const STATUS_ORDER = [
        RepairOrderStatus::Draft->value,
        RepairOrderStatus::Estimate->value,
        RepairOrderStatus::WaitingApproval->value,
        RepairOrderStatus::Approved->value,
        RepairOrderStatus::ReadyForWork->value,
        RepairOrderStatus::WaitingParts->value,
        RepairOrderStatus::InProgress->value,
        RepairOrderStatus::QualityCheck->value,
        RepairOrderStatus::Completed->value,
        RepairOrderStatus::Invoiced->value,
        RepairOrderStatus::ReadyPickup->value,
    ];

    public function __construct(
        private readonly EstimateTotalsCalculator $totals,
    ) {}

    public function forOpenQueue(): ShopDashboardProjection
    {
        $repairOrders = RepairOrder::query()
            ->with(['lines.concern', 'customer', 'vehicle'])
            ->whereIn('status', RepairOrderStatus::operationalQueueValues())
            ->orderBy('id')
            ->get();

        $pendingCents = 0;
        $declinedCents = 0;
        $approvedCents = 0;

        /** @var array<string, array{label: string, car_count: int, pending_cents: int, declined_cents: int, approved_cents: int}> $buckets */
        $buckets = [];

        foreach ($repairOrders as $repairOrder) {
            $status = $repairOrder->workboardLaneStatus();
            $key = $status->value;
            $buckets[$key] ??= [
                'label' => $status->label(),
                'car_count' => 0,
                'pending_cents' => 0,
                'declined_cents' => 0,
                'approved_cents' => 0,
            ];

            $roPending = $this->totals->recommendedTotalsForRead($repairOrder)->totalCents();
            $roDeclined = $this->totals->declinedTotalsForRead($repairOrder)->totalCents();
            $roApproved = $this->totals->approvedTotalsForRead($repairOrder)->totalCents();

            $pendingCents += $roPending;
            $declinedCents += $roDeclined;
            $approvedCents += $roApproved;

            $buckets[$key]['car_count']++;
            $buckets[$key]['pending_cents'] += $roPending;
            $buckets[$key]['declined_cents'] += $roDeclined;
            $buckets[$key]['approved_cents'] += $roApproved;
        }

        $carCount = $repairOrders->count();
        $totalWrittenCents = $pendingCents + $declinedCents + $approvedCents;
        $aroCents = $carCount > 0 ? (int) round($approvedCents / $carCount) : 0;
        $closeRatioPercent = $totalWrittenCents > 0
            ? round(($approvedCents / $totalWrittenCents) * 100, 1)
            : null;

        $maxCars = max(1, ...array_map(
            static fn (array $bucket): int => $bucket['car_count'],
            $buckets !== [] ? array_values($buckets) : [['car_count' => 0]],
        ));

        $statusRows = [];
        foreach (self::STATUS_ORDER as $statusKey) {
            if (! isset($buckets[$statusKey])) {
                continue;
            }

            $bucket = $buckets[$statusKey];
            $statusRows[] = $this->statusRow($statusKey, $bucket, $maxCars);
            unset($buckets[$statusKey]);
        }

        foreach ($buckets as $statusKey => $bucket) {
            $statusRows[] = $this->statusRow($statusKey, $bucket, $maxCars);
        }

        $statusRows = $this->markPeakRows($statusRows);
        $chartRows = $this->chartRowsByVolume($statusRows);

        $today = OperationalReportDateScope::shopNow();
        $openQueueUrl = $this->inventoryUrl();
        $pendingUrl = $this->inventoryUrl(disposition: RepairOrderConcernDisposition::Recommended);
        $declinedUrl = $this->inventoryUrl(disposition: RepairOrderConcernDisposition::Declined);
        $approvedUrl = $this->inventoryUrl(disposition: RepairOrderConcernDisposition::Approved);

        return new ShopDashboardProjection(
            rangeLabel: 'Open queue',
            asOfLabel: OperationalReportDateScope::shopRangeLabel($today, $today),
            asOfDate: OperationalReportDateScope::shopDateString($today),
            carCount: $carCount,
            pendingCents: $pendingCents,
            declinedCents: $declinedCents,
            approvedCents: $approvedCents,
            totalWrittenCents: $totalWrittenCents,
            aroCents: $aroCents,
            closeRatioPercent: $closeRatioPercent,
            pendingLabel: $this->money($pendingCents),
            declinedLabel: $this->money($declinedCents),
            approvedLabel: $this->money($approvedCents),
            aroLabel: $this->money($aroCents),
            kpis: [
                [
                    'label' => 'Car Count',
                    'value' => (string) $carCount,
                    'hint' => 'Open repair orders · click to list',
                    'url' => $openQueueUrl,
                ],
                [
                    'label' => 'Pending Sales',
                    'value' => $this->money($pendingCents),
                    'hint' => 'Recommended · awaiting decision',
                    'url' => $pendingUrl,
                ],
                [
                    'label' => 'Declined Sales',
                    'value' => $this->money($declinedCents),
                    'hint' => 'Customer declined lines',
                    'url' => $declinedUrl,
                ],
                [
                    'label' => 'Approved Sales',
                    'value' => $this->money($approvedCents),
                    'hint' => 'Approved invoiceable work',
                    'url' => $approvedUrl,
                ],
                [
                    'label' => 'ARO',
                    'value' => $this->money($aroCents),
                    'hint' => 'Approved ÷ car count',
                    'url' => $approvedUrl,
                ],
                [
                    'label' => 'Close Ratio',
                    'value' => $closeRatioPercent !== null ? number_format($closeRatioPercent, 1).'%' : 'n/a',
                    'hint' => 'Approved ÷ total written',
                    'url' => $openQueueUrl,
                ],
            ],
            statusRows: $statusRows,
            chartRows: $chartRows,
            concentrationLine: $this->concentrationLine($statusRows, $carCount),
            jobBoardUrl: route('operations.index'),
            openQueueUrl: $openQueueUrl,
            pendingUrl: $pendingUrl,
            declinedUrl: $declinedUrl,
            approvedUrl: $approvedUrl,
            footnote: 'Posted sales live on Reports / Day Review',
        );
    }

    /**
     * @param  array{label: string, car_count: int, pending_cents: int, declined_cents: int, approved_cents: int}  $bucket
     * @return array{
     *     key: string,
     *     label: string,
     *     car_count: int,
     *     pending_cents: int,
     *     declined_cents: int,
     *     approved_cents: int,
     *     pending_label: string,
     *     declined_label: string,
     *     approved_label: string,
     *     aro_cents: int,
     *     aro_label: string,
     *     bar_pct: float,
     *     peak: bool,
     *     status_url: string,
     *     pending_url: string,
     *     declined_url: string,
     *     approved_url: string
     * }
     */
    private function statusRow(string $key, array $bucket, int $maxCars): array
    {
        $cars = $bucket['car_count'];
        $approved = $bucket['approved_cents'];
        $aro = $cars > 0 ? (int) round($approved / $cars) : 0;
        $status = RepairOrderStatus::tryFrom($key);

        return [
            'key' => $key,
            'label' => $bucket['label'],
            'car_count' => $cars,
            'pending_cents' => $bucket['pending_cents'],
            'declined_cents' => $bucket['declined_cents'],
            'approved_cents' => $approved,
            'pending_label' => $this->money($bucket['pending_cents']),
            'declined_label' => $this->money($bucket['declined_cents']),
            'approved_label' => $this->money($approved),
            'aro_cents' => $aro,
            'aro_label' => $this->money($aro),
            'bar_pct' => round(($cars / max(1, $maxCars)) * 100, 1),
            'peak' => false,
            'status_url' => $this->inventoryUrl(status: $status),
            'pending_url' => $this->inventoryUrl(
                status: $status,
                disposition: RepairOrderConcernDisposition::Recommended,
            ),
            'declined_url' => $this->inventoryUrl(
                status: $status,
                disposition: RepairOrderConcernDisposition::Declined,
            ),
            'approved_url' => $this->inventoryUrl(
                status: $status,
                disposition: RepairOrderConcernDisposition::Approved,
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $statusRows
     * @return list<array<string, mixed>>
     */
    private function markPeakRows(array $statusRows): array
    {
        $counts = array_map(static fn (array $row): int => (int) $row['car_count'], $statusRows);
        $maxCars = $counts !== [] ? max($counts) : 0;
        $second = 0;
        rsort($counts);
        foreach ($counts as $count) {
            if ($count < $maxCars) {
                $second = $count;
                break;
            }
        }

        $emphasizePeak = $maxCars > 1 && $maxCars > $second;

        foreach ($statusRows as $index => $row) {
            $statusRows[$index]['peak'] = $emphasizePeak && (int) $row['car_count'] === $maxCars;
        }

        return $statusRows;
    }

    /**
     * @param  list<array<string, mixed>>  $statusRows
     * @return list<array<string, mixed>>
     */
    private function chartRowsByVolume(array $statusRows): array
    {
        $orderIndex = array_flip(self::STATUS_ORDER);
        $chartRows = $statusRows;
        usort(
            $chartRows,
            static function (array $left, array $right) use ($orderIndex): int {
                $byCount = (int) $right['car_count'] <=> (int) $left['car_count'];
                if ($byCount !== 0) {
                    return $byCount;
                }

                return ($orderIndex[$left['key']] ?? 999) <=> ($orderIndex[$right['key']] ?? 999);
            },
        );

        return $chartRows;
    }

    /**
     * @param  list<array<string, mixed>>  $statusRows
     */
    private function concentrationLine(array $statusRows, int $carCount): ?string
    {
        $actionKeys = [
            RepairOrderStatus::Estimate->value,
            RepairOrderStatus::WaitingApproval->value,
        ];
        $focusRows = array_values(array_filter(
            $statusRows,
            static fn (array $row): bool => in_array($row['key'], $actionKeys, true),
        ));

        if ($focusRows === []) {
            $focusRows = array_values(array_filter(
                $statusRows,
                static fn (array $row): bool => (bool) $row['peak'],
            ));
        }

        if ($focusRows === [] || $carCount < 1) {
            return null;
        }

        $count = 0;
        $labels = [];
        foreach ($focusRows as $row) {
            $count += (int) $row['car_count'];
            $labels[] = (string) $row['label'];
        }

        $joined = match (count($labels)) {
            1 => $labels[0],
            2 => $labels[0].' and '.$labels[1],
            default => implode(', ', array_slice($labels, 0, -1)).', and '.$labels[array_key_last($labels)],
        };

        return $count.' of '.$carCount.' in '.$joined;
    }

    private function inventoryUrl(
        ?RepairOrderStatus $status = null,
        ?RepairOrderConcernDisposition $disposition = null,
    ): string {
        $query = ['open' => '1'];

        if ($status !== null) {
            $query['status'] = $status->value;
        }

        if ($disposition !== null) {
            $query['disposition'] = $disposition->value;
        }

        return route('operations.repair-orders.index', $query);
    }

    private function money(int $cents): string
    {
        return Money::ofMinor($cents, 'USD')->formatTo('en_US');
    }
}
