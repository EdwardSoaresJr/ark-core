<?php

namespace App\Ark\Operations\Reports;

use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class OperationalReportPaymentReconciliation
{
    public function __construct(
        private readonly Carbon $from,
        private readonly Carbon $to,
    ) {}

    /**
     * @return array{
     *     rows: list<array{
     *         key: string,
     *         label: string,
     *         amount: string,
     *         note: string,
     *         emphasis: bool,
     *         tone: 'subtract'|'add'|'base'|'total'|null,
     *         details: list<array{
     *             repair_order_id: int,
     *             repair_order_pk: int,
     *             customer: string,
     *             vehicle: string,
     *             amount: string,
     *             payment_at: string,
     *             posted_at: string|null
     *         }>
     *     }>,
     *     posted_ro_summary: array{
     *         service: string,
     *         tax: string,
     *         total: string,
     *         details: list<array{
     *             repair_order_id: int,
     *             repair_order_pk: int,
     *             customer: string,
     *             vehicle: string,
     *             amount: string,
     *             posted_at: string
     *         }>
     *     },
     *     reconciled_cents: int,
     *     posted_ro_summary_total_cents: int,
     *     delta_cents: int,
     *     delta_label: string,
     *     reconciles: bool
     * }
     */
    public function summary(): array
    {
        $salesPostedIds = OperationalReportDateScope::salesPostedBetween(
            RepairOrder::query(),
            $this->from,
            $this->to,
        )->pluck('id');

        $totalCashiered = $this->ledgerBucket($this->ledgerInRangeQuery());
        $advancePay = $this->ledgerBucket(
            $this->ledgerInRangeQuery()->where(function (Builder $query): void {
                $query
                    ->whereNull('repair_orders.posted_at')
                    ->orWhere('repair_orders.posted_at', '>', $this->to);
            }),
        );
        $previousAdvancedPay = $this->ledgerBucket(
            $this->ledgerBaseQuery()
                ->where('repair_order_ledger_entries.recorded_at', '<', $this->from)
                ->whereBetween('repair_orders.posted_at', [$this->from, $this->to])
                ->whereIn('repair_order_ledger_entries.repair_order_id', $salesPostedIds),
        );
        // Future-posted ROs stay in advance_pay only — never also in cleared_from_ar.
        $clearedFromAr = $this->ledgerBucket(
            $this->ledgerInRangeQuery()
                ->whereNotNull('repair_orders.posted_at')
                ->where('repair_orders.posted_at', '<', $this->from),
        );
        $legacyCarryoverExcluded = $this->legacyCarryoverPaymentsBucket($salesPostedIds);
        $postedToArCents = 0;

        $reconciledCents = $totalCashiered['cents']
            - $advancePay['cents']
            + $previousAdvancedPay['cents']
            - $clearedFromAr['cents']
            - $legacyCarryoverExcluded['cents']
            + $postedToArCents;

        $serviceCents = $this->postedServiceSalesCents();
        $taxCents = $this->postedTaxCollectedCents();
        $postedRoSummary = $this->postedRoSummary($serviceCents, $taxCents);
        $deltaCents = $reconciledCents - $postedRoSummary['total_cents'];

        $rows = [
            $this->row('total_cashiered', 'Total cashiered', $totalCashiered, 'Payments + deposits dated in range', 'base'),
            $this->row('advance_pay', 'Advance pay', $advancePay, 'Payments on ROs not posted in this range', 'subtract', subtractDisplay: true),
            $this->row('previous_advanced_pay', 'Previous advanced pay', $previousAdvancedPay, 'Pre-range payments on ROs posted in this range', 'add'),
            $this->row('cleared_from_ar', 'Cleared from A/R', $clearedFromAr, 'Payments in range on ROs posted before this range', 'subtract', subtractDisplay: true),
            $this->row('legacy_carryover', 'Legacy carryover excluded', $legacyCarryoverExcluded, 'Posted in range but opened before reporting floor — not in Sales Posted', 'subtract', subtractDisplay: true),
        ];

        if ($postedToArCents !== 0) {
            $rows[] = $this->row('posted_to_ar', 'Posted to A/R', ['cents' => $postedToArCents, 'details' => []], 'A/R posting adds back when enabled', 'add');
        }

        $rows[] = $this->row('reconciled', 'Reconciled to posted sales', ['cents' => $reconciledCents, 'details' => []], 'Should match posted invoice total below', 'total');

        return [
            'rows' => $rows,
            'posted_ro_summary' => [
                'service' => $this->money($serviceCents),
                'tax' => $this->money($taxCents),
                'total' => $this->money($postedRoSummary['total_cents']),
                'details' => $postedRoSummary['details'],
            ],
            'reconciled_cents' => $reconciledCents,
            'posted_ro_summary_total_cents' => $postedRoSummary['total_cents'],
            'delta_cents' => $deltaCents,
            'delta_label' => $this->money(abs($deltaCents)).($deltaCents === 0 ? '' : ($deltaCents > 0 ? ' over' : ' under')),
            'reconciles' => abs($deltaCents) <= 1,
        ];
    }

    /**
     * @param  array{cents: int, details: list<array{repair_order_id: int, repair_order_pk: int, customer: string, vehicle: string, amount: string, payment_at: string, posted_at: string|null}>}  $bucket
     * @return array{
     *     key: string,
     *     label: string,
     *     amount: string,
     *     note: string,
     *     emphasis: bool,
     *     tone: 'subtract'|'add'|'base'|'total'|null,
     *     details: list<array{repair_order_id: int, repair_order_pk: int, customer: string, vehicle: string, amount: string, payment_at: string, posted_at: string|null}>
     * }
     */
    private function row(
        string $key,
        string $label,
        array $bucket,
        string $note,
        ?string $tone,
        bool $subtractDisplay = false,
    ): array {
        $cents = $bucket['cents'];
        $displayCents = $subtractDisplay && $cents > 0 ? -$cents : $cents;

        return [
            'key' => $key,
            'label' => $label,
            'amount' => $this->money($displayCents),
            'note' => $note,
            'emphasis' => in_array($tone, ['base', 'total'], true),
            'tone' => $tone,
            'details' => $bucket['details'],
        ];
    }

    /**
     * @return array{cents: int, details: list<array{repair_order_id: int, repair_order_pk: int, customer: string, vehicle: string, amount: string, payment_at: string, posted_at: string|null}>}
     */
    private function ledgerBucket(Builder $query): array
    {
        $aggregates = (clone $query)
            ->select('repair_order_ledger_entries.repair_order_id')
            ->selectRaw('COALESCE(SUM(repair_order_ledger_entries.amount_cents), 0) as amount_cents')
            ->selectRaw('MIN(repair_order_ledger_entries.recorded_at) as first_recorded_at')
            ->selectRaw('MAX(repair_order_ledger_entries.recorded_at) as last_recorded_at')
            ->groupBy('repair_order_ledger_entries.repair_order_id')
            ->orderByDesc('amount_cents')
            ->get();

        if ($aggregates->isEmpty()) {
            return ['cents' => 0, 'details' => []];
        }

        $repairOrders = RepairOrder::query()
            ->with([
                'customer:id,first_name,last_name',
                'vehicle:id,year,make,model',
            ])
            ->whereIn('id', $aggregates->pluck('repair_order_id'))
            ->get()
            ->keyBy('id');

        $details = $aggregates
            ->map(function ($aggregate) use ($repairOrders): ?array {
                $repairOrder = $repairOrders->get($aggregate->repair_order_id);

                if ($repairOrder === null) {
                    return null;
                }

                $firstRecordedAt = Carbon::parse($aggregate->first_recorded_at);
                $lastRecordedAt = Carbon::parse($aggregate->last_recorded_at);

                return [
                    'repair_order_id' => (int) $repairOrder->repair_order_id,
                    'repair_order_pk' => (int) $repairOrder->id,
                    'customer' => $repairOrder->customer->name,
                    'vehicle' => $repairOrder->vehicle->display_name,
                    'amount' => $this->money((int) $aggregate->amount_cents),
                    'payment_at' => $this->paymentAtLabel($firstRecordedAt, $lastRecordedAt),
                    'posted_at' => $repairOrder->posted_at !== null
                        ? $this->shopDateTimeLabel($repairOrder->posted_at)
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'cents' => (int) $aggregates->sum('amount_cents'),
            'details' => $details,
        ];
    }

    /**
     * @return array{total_cents: int, details: list<array{repair_order_id: int, repair_order_pk: int, customer: string, vehicle: string, amount: string, posted_at: string}>}
     */
    private function postedRoSummary(int $serviceCents, int $taxCents): array
    {
        $postedRepairOrders = OperationalReportDateScope::salesPostedBetween(RepairOrder::query(), $this->from, $this->to)
            ->with([
                'customer:id,first_name,last_name',
                'vehicle:id,year,make,model',
            ])
            ->orderByDesc('posted_at')
            ->get();

        $totalsByRepairOrder = OperationalReportTotals::postedSalesCentsByRepairOrderId($postedRepairOrders->pluck('id'));
        $totalCents = (int) $totalsByRepairOrder->sum();
        $details = $postedRepairOrders
            ->map(function (RepairOrder $repairOrder) use ($totalsByRepairOrder): array {
                return [
                    'repair_order_id' => (int) $repairOrder->repair_order_id,
                    'repair_order_pk' => (int) $repairOrder->id,
                    'customer' => $repairOrder->customer->name,
                    'vehicle' => $repairOrder->vehicle->display_name,
                    'amount' => $this->money((int) ($totalsByRepairOrder[$repairOrder->id] ?? 0)),
                    'posted_at' => $repairOrder->posted_at !== null
                        ? $this->shopDateTimeLabel($repairOrder->posted_at)
                        : '—',
                ];
            })
            ->values()
            ->all();

        return [
            'total_cents' => $totalCents,
            'details' => $details,
        ];
    }

    /**
     * Payments on ROs posted in range but excluded from {@see OperationalReportDateScope::salesPostedBetween()}.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $salesPostedIds
     * @return array{cents: int, details: list<array{repair_order_id: int, repair_order_pk: int, customer: string, vehicle: string, amount: string, payment_at: string, posted_at: string|null}>}
     */
    private function legacyCarryoverPaymentsBucket(\Illuminate\Support\Collection $salesPostedIds): array
    {
        $postedInRangeIds = RepairOrder::query()
            ->whereNotNull('posted_at')
            ->whereBetween('posted_at', [$this->from, $this->to])
            ->pluck('id');

        $excludedIds = $postedInRangeIds->diff($salesPostedIds)->values();

        if ($excludedIds->isEmpty()) {
            return ['cents' => 0, 'details' => []];
        }

        return $this->ledgerBucket(
            $this->ledgerInRangeQuery()->whereIn('repair_order_ledger_entries.repair_order_id', $excludedIds),
        );
    }

    private function postedServiceSalesCents(): int
    {
        return OperationalReportTotals::sumTotalCents(
            OperationalReportDateScope::salesPostedBetween(RepairOrder::query(), $this->from, $this->to)->pluck('id'),
        );
    }

    private function postedTaxCollectedCents(): int
    {
        return (int) OperationalReportTotals::soldLineQuery()
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_lines.repair_order_id')
            ->tap(fn (Builder $query): Builder => OperationalReportDateScope::applySalesPostedBetweenOnJoinedRepairOrders($query, $this->from, $this->to))
            ->sum('repair_order_lines.tax_cents');
    }

    /**
     * All drawer activity — no trustworthy floor. Must match {@see OperationalReportTotals::cashCollectedCents()}.
     *
     * @return Builder<RepairOrderLedgerEntry>
     */
    private function ledgerBaseQuery(): Builder
    {
        return RepairOrderLedgerEntry::query()
            ->from('repair_order_ledger_entries')
            ->active()
            ->whereIn('repair_order_ledger_entries.entry_type', [
                LedgerEntryType::Payment,
                LedgerEntryType::Deposit,
            ])
            ->join('repair_orders', 'repair_orders.id', '=', 'repair_order_ledger_entries.repair_order_id');
    }

    /**
     * @return Builder<RepairOrderLedgerEntry>
     */
    private function ledgerInRangeQuery(): Builder
    {
        return $this->ledgerBaseQuery()
            ->whereBetween('repair_order_ledger_entries.recorded_at', [$this->from, $this->to]);
    }

    private function paymentAtLabel(Carbon $firstRecordedAt, Carbon $lastRecordedAt): string
    {
        if ($firstRecordedAt->equalTo($lastRecordedAt)) {
            return $this->shopDateTimeLabel($firstRecordedAt);
        }

        return $this->shopDateTimeLabel($firstRecordedAt).' – '.$this->shopDateTimeLabel($lastRecordedAt);
    }

    private function shopDateTimeLabel(Carbon $instant): string
    {
        return $instant
            ->copy()
            ->timezone(OperationalReportDateScope::displayTimezone())
            ->format('M j, g:i A');
    }

    private function money(int $cents): string
    {
        $prefix = $cents < 0 ? '−' : '';

        return $prefix.'$'.Money::ofMinor(abs($cents), 'USD')->getAmount()->toScale(2)->__toString();
    }
}
