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
     * Display order for status lanes (Tekmetric-like left-to-right flow).
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

        $today = OperationalReportDateScope::shopNow();
        $openQueueUrl = $this->inventoryUrl();
        $pendingUrl = $this->inventoryUrl(disposition: RepairOrderConcernDisposition::Recommended);
        $declinedUrl = $this->inventoryUrl(disposition: RepairOrderConcernDisposition::Declined);
        $approvedUrl = $this->inventoryUrl(disposition: RepairOrderConcernDisposition::Approved);

        return new ShopDashboardProjection(
            rangeLabel: 'Open queue · '.OperationalReportDateScope::shopDateString($today),
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
            jobBoardUrl: route('operations.index'),
            openQueueUrl: $openQueueUrl,
            pendingUrl: $pendingUrl,
            declinedUrl: $declinedUrl,
            approvedUrl: $approvedUrl,
            footnote: 'Click a number to open matching ROs · Posted sales live on Reports / Day Review',
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
     *     aro_label: string,
     *     bar_pct: float,
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
            'aro_label' => $this->money($aro),
            'bar_pct' => round(($cars / max(1, $maxCars)) * 100, 1),
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
