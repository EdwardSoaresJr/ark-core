<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;

/**
 * Money on the mobile RO — read-only projection of authoritative estimate
 * totals and Financial Position. Mobile renders dollars; it never derives them.
 *
 * Owe-today: FinancialPositionProjection only.
 * GET-safe: never recalculates/saves.
 */
final class MobileEstimateProjection
{
    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
    ) {}

    /**
     * Compact money summary for the workspace header + command-bar gating.
     *
     * @return array<string, mixed>
     */
    public function summary(RepairOrder $repairOrder): array
    {
        $totals = $this->calculator->totalsFor($repairOrder);
        $approvedCents = $this->calculator->approvedTotalsForRead($repairOrder)->totalCents();
        $waitingCents = $this->calculator->recommendedTotalsForRead($repairOrder)->totalCents();
        $estimateCents = $totals->totalCents();
        $position = \App\Ark\Operations\Financial\FinancialPositionProjection::for($repairOrder);
        $hasInvoice = $position->hasIssuedFinalInvoice();
        $balanceCents = $position->customerOwesTodayCents;

        return [
            'estimate_total_cents' => $estimateCents,
            'estimate_total_label' => $totals->format($estimateCents),
            // "What is approved vs waiting?" — approved invoiceable work and the
            // recommended work still awaiting a decision (ARO). Independent
            // buckets: the estimate total drops recommended work once anything
            // is approved, so waiting is not (estimate − approved).
            'approved_total_cents' => $approvedCents,
            'approved_total_label' => $totals->format($approvedCents),
            'waiting_total_cents' => $waitingCents,
            'waiting_total_label' => $totals->format($waitingCents),
            'has_unapproved_work' => $waitingCents > 0,
            'has_lines' => $repairOrder->concerns
                ->contains(fn (RepairOrderConcern $concern): bool => $concern->lines
                    ->contains(fn (RepairOrderLine $line): bool => ! $line->type->isNote())),
            'has_issued_invoice' => $hasInvoice,
            'balance_due_cents' => $balanceCents,
            'balance_due_label' => $totals->format($balanceCents),
            'balance_due_outstanding' => $balanceCents > 0,
            'customer_owes_today_cents' => $position->customerOwesTodayCents,
            'projected_balance_label' => $position->projectedBalanceLabel(),
            'contract_source' => $position->contractSource->value,
        ];
    }

    /**
     * Full estimate detail for the Estimate section — priced line items grouped
     * by concern, plus the parts/labor/fees/tax breakdown.
     *
     * @return array<string, mixed>
     */
    public function detail(RepairOrder $repairOrder, ?User $viewer = null, ?MobileStaffAccess $access = null): array
    {
        $repairOrder->loadMissing(['concerns.lines', 'lines.concern', 'customer']);
        $totals = $this->calculator->totalsFor($repairOrder);
        $canEdit = $viewer !== null
            && $access !== null
            && ! $repairOrder->isTerminal()
            && $access->canSetConcernDisposition($viewer, $repairOrder);

        $groups = $repairOrder->concerns
            ->map(function (RepairOrderConcern $concern) use ($totals, $canEdit): array {
                $lines = $concern->lines
                    ->reject(fn (RepairOrderLine $line): bool => $line->type->isNote())
                    ->map(fn (RepairOrderLine $line): array => $this->lineRow($line, $totals, $canEdit))
                    ->values()
                    ->all();

                return [
                    'concern_id' => $concern->id,
                    'title' => $concern->summary,
                    'disposition' => $concern->disposition->value,
                    'disposition_label' => $concern->disposition->label(),
                    'subtotal_label' => $totals->format($totals->concernSubtotalCents($concern->id)),
                    'lines' => $lines,
                ];
            })
            ->filter(fn (array $group): bool => $group['lines'] !== [])
            ->values()
            ->all();

        return [
            ...$this->summary($repairOrder),
            'parts_label' => $totals->format($totals->partsCents()),
            'labor_label' => $totals->format($totals->laborCents()),
            'fees_label' => $totals->format($totals->feesCents()),
            'tax_label' => $totals->format($totals->taxCents()),
            'subtotal_label' => $totals->format($totals->subtotalBeforeTaxCents()),
            'groups' => $groups,
            'is_empty' => $groups === [],
            'estimate_editing' => $canEdit ? $this->estimateEditing($repairOrder, $totals) : null,
        ];
    }

    public function concernSubtotalLabel(RepairOrder $repairOrder, int $concernId): ?string
    {
        $totals = $this->calculator->totalsFor($repairOrder);
        $cents = $totals->concernSubtotalCents($concernId);

        if ($cents <= 0) {
            return null;
        }

        return $totals->format($cents);
    }

    /**
     * @return array<string, mixed>
     */
    private function lineRow(RepairOrderLine $line, EstimateTotals $totals, bool $canEdit): array
    {
        return [
            'id' => $line->id,
            'repair_order_concern_id' => $line->repair_order_concern_id,
            'type' => $line->type->value,
            'type_label' => $line->type->staffLabel(),
            'description' => $line->description,
            'quantity' => (float) $line->quantity,
            'unit_price_label' => $totals->format((int) ($line->unit_price_cents ?? 0)),
            'total_label' => $totals->format((int) ($line->total_cents ?? 0)),
            'total_cents' => (int) ($line->total_cents ?? 0),
            'can_edit' => $canEdit,
            'can_delete' => $canEdit,
            'edit_inputs' => $canEdit ? $this->lineEditInputs($line) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineEditInputs(RepairOrderLine $line): array
    {
        $inputs = [
            'repair_order_concern_id' => $line->repair_order_concern_id,
            'type' => $line->type->value,
            'description' => $line->description,
            'quantity' => (float) $line->quantity,
        ];

        if ($line->type->isPart()) {
            if ($line->part_cost_cents !== null) {
                $inputs['part_cost'] = $this->formatMoneyInput((int) $line->part_cost_cents);
            }

            if ($line->is_overridden && $line->unit_price_cents !== null) {
                $inputs['unit_price'] = $this->formatMoneyInput((int) $line->unit_price_cents);
            }
        }

        return $inputs;
    }

    private function formatMoneyInput(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    private function estimateEditing(RepairOrder $repairOrder, EstimateTotals $totals): array
    {
        return [
            'can_edit' => true,
            'labor_rate_label' => $totals->format(ShopSettings::current()->defaultLaborRateCents()),
            'concerns' => $repairOrder->concerns
                ->map(fn (RepairOrderConcern $concern): array => [
                    'id' => $concern->id,
                    'title' => $concern->summary,
                ])
                ->values()
                ->all(),
            'line_types' => [
                [
                    'value' => RepairOrderLineType::Labor->value,
                    'label' => RepairOrderLineType::Labor->staffLabel(),
                    'quantity_label' => 'Hours',
                ],
                [
                    'value' => RepairOrderLineType::Part->value,
                    'label' => RepairOrderLineType::Part->staffLabel(),
                    'quantity_label' => 'Qty',
                ],
            ],
        ];
    }
}
