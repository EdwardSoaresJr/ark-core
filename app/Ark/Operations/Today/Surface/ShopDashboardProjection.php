<?php

namespace App\Ark\Operations\Today\Surface;

/**
 * Tekmetric-style shop dashboard — open-queue disposition money + status lanes.
 * Disposable projection; rebuild from RepairOrder + estimate line authority.
 */
final readonly class ShopDashboardProjection
{
    /**
     * @param  list<array{label: string, value: string, hint: string|null, url: string|null}>  $kpis
     * @param  list<array{
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
     * }>  $statusRows
     */
    public function __construct(
        public string $rangeLabel,
        public int $carCount,
        public int $pendingCents,
        public int $declinedCents,
        public int $approvedCents,
        public int $totalWrittenCents,
        public int $aroCents,
        public ?float $closeRatioPercent,
        public string $pendingLabel,
        public string $declinedLabel,
        public string $approvedLabel,
        public string $aroLabel,
        public array $kpis,
        public array $statusRows,
        public string $jobBoardUrl,
        public string $openQueueUrl,
        public string $pendingUrl,
        public string $declinedUrl,
        public string $approvedUrl,
        public string $footnote,
    ) {}
}
