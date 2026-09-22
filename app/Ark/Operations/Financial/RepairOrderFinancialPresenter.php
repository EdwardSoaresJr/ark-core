<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\Capture\PaymentCaptureMethod;
use App\Ark\Operations\Payments\Capture\PaymentCaptureReadinessProjection;
use App\Ark\Operations\Payments\CardPresentCaptureProjection;
use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderPosting;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use Brick\Money\Money;
use Illuminate\Support\Collection;

final class RepairOrderFinancialPresenter
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly RepairOrderCloseoutAuthority $closeout,
        private readonly RepairOrderPosting $posting,
        private readonly EstimateTotalsCalculator $totalsCalculator,
        private readonly RepairOrderDefaultDepositCalculator $defaultDepositCalculator,
        private readonly RepairOrderDepositRecordingGuard $depositGuard,
        private readonly CardPresentCaptureProjection $cardCapture,
    ) {}

    /**
     * Two money axes (plus posted sales):
     * - Owe today — FinancialPositionProjection (operational / customer-facing position).
     * - Settlement — BalanceDueResult (issued-invoice paid state + pay/waive/close/post gates).
     * - Posted sales — repair_orders.posted_at (not a balance).
     *
     * Legacy balanceDue* keys alias settlement when an invoice is issued so payment forms
     * cannot silently collect against owe-today while gates use settlement.
     *
     * @return array<string, mixed>
     */
    public function for(
        RepairOrder $repairOrder,
        EstimateTotals $estimateTotals,
        ?RepairOrderBalanceProjection $balanceProjection = null,
    ): array {
        $repairOrder->loadMissing('customer');
        $balanceProjection ??= $this->balanceDue->projectForRepairOrder($repairOrder);
        $balance = $balanceProjection->balance;
        $invoice = $balanceProjection->invoice;
        $hasApprovedInvoiceableWork = $repairOrder->status->is(RepairOrderStatus::ReadyPickup)
            && $this->totalsCalculator->hasApprovedInvoiceableWork($repairOrder);

        $workflowPosture = $this->workflowPosture($repairOrder, $balance);
        $ledgerEntries = $this->ledgerHistory($repairOrder);
        $defaultDeposit = $this->defaultDepositCalculator->forRepairOrder($repairOrder);
        $depositWorkspaceLines = $this->defaultDepositCalculator->workspaceLines($repairOrder);
        $closeoutBlockingReason = $this->closeout->blockingReason($repairOrder, balance: $balance, invoice: $invoice);
        $isPaid = $balance->isPaid();
        $canPost = $repairOrder->posted_at === null
            && $repairOrder->close_variant_key !== 'lost'
            && $balance->hasIssuedInvoice
            && $isPaid;
        $postBlockingReason = $canPost
            ? null
            : $this->posting->blockingReason($repairOrder, balance: $balance, invoice: $invoice);

        $remainingSuggestedDepositCents = $this->depositGuard->remainingSuggestedDepositCents(
            $repairOrder,
            $balance,
        );
        $canRecordDeposit = $this->canRecordDeposit($repairOrder, $balance);
        // Satisfied is strictly "remaining computed and is zero" — reuse remaining; do not re-enter the guard.
        $suggestedDepositSatisfied = $remainingSuggestedDepositCents !== null
            && $remainingSuggestedDepositCents === 0;

        $approvedWorkTotalCents = $estimateTotals->totalCents();
        $position = FinancialPositionProjection::for($repairOrder);
        $oweTodayCents = $position->customerOwesTodayCents;
        $settlementBalanceDueCents = $balance->balanceDueCents;
        $invoiceDriftCents = $balance->hasIssuedInvoice
            ? ($approvedWorkTotalCents - $balance->invoiceTotalCents)
            : 0;
        $invoiceNeedsRefresh = $balance->hasIssuedInvoice && $invoiceDriftCents !== 0;
        $invoiceRefreshBlockedBySettlement = $invoiceNeedsRefresh
            && (
                $balance->writeOffsCents > 0
                || $balance->refundsAppliedCents > 0
                || $balance->creditsAppliedCents > 0
            );
        $canRefreshInvoice = $invoiceNeedsRefresh
            && ! $invoiceRefreshBlockedBySettlement
            && ! $repairOrder->isTerminal();
        $oweTodayDiffersFromSettlement = $balance->hasIssuedInvoice
            && $oweTodayCents !== $settlementBalanceDueCents;

        return [
            'workflowPosture' => $workflowPosture,
            'workflowLabel' => $this->workflowLabel($workflowPosture),
            'workflowHint' => $this->workflowHint($repairOrder, $balance, $workflowPosture),
            'hasIssuedInvoice' => $balance->hasIssuedInvoice,
            'invoice' => $invoice,
            'invoiceStatusLabel' => $balance->hasIssuedInvoice
                ? $balance->invoiceStatus->label()
                : 'Not issued',
            'estimateTotal' => $this->formatCents($estimateTotals->totalCents()),
            'invoiceTotal' => $balance->hasIssuedInvoice
                ? $this->formatCents($balance->invoiceTotalCents)
                : null,
            'depositsApplied' => $this->formatCents($balance->depositsAppliedCents),
            'paymentsApplied' => $this->formatCents($balance->paymentsAppliedCents),
            'refundsApplied' => $this->formatCents($balance->refundsAppliedCents),
            'creditsApplied' => $this->formatCents($balance->creditsAppliedCents),
            'adjustments' => $this->formatCents($balance->adjustmentsCents),
            'writeOffs' => $this->formatCents($balance->writeOffsCents),
            'writeOffsCents' => $balance->writeOffsCents,
            'collectedCents' => $balance->depositsAppliedCents + $balance->paymentsAppliedCents,
            'collected' => $this->formatCents($balance->depositsAppliedCents + $balance->paymentsAppliedCents),
            'wouldHaveCost' => $balance->hasIssuedInvoice
                ? $this->formatCents($balance->invoiceTotalCents)
                : null,
            'wouldHaveCostCents' => $balance->hasIssuedInvoice ? $balance->invoiceTotalCents : null,
            'waived' => $this->formatCents($balance->writeOffsCents),
            'waivedCents' => $balance->writeOffsCents,
            'collectionDisposition' => $this->collectionDisposition($repairOrder)->value,
            'collectionDispositionLabel' => $this->collectionDisposition($repairOrder)->label(),
            'collectionDispositionReason' => filled($repairOrder->collection_disposition_reason)
                ? (string) $repairOrder->collection_disposition_reason
                : null,
            'collectionWaiverLabel' => $this->collectionDisposition($repairOrder)->waiverCustomerLabel(),
            'excludesFromPostedSales' => $this->collectionDisposition($repairOrder)->excludesFromPostedSales(),
            // Owe today — FinancialPositionProjection
            'oweTodayCents' => $oweTodayCents,
            'oweToday' => $this->formatCents($oweTodayCents),
            'oweTodayDecimal' => $this->decimalCents($oweTodayCents),
            'oweTodayDiffersFromSettlement' => $oweTodayDiffersFromSettlement,
            // Settlement — BalanceDueResult (issued invoice contract)
            'settlementBalanceDueCents' => $settlementBalanceDueCents,
            'settlementBalanceDue' => $balance->hasIssuedInvoice
                ? $this->formatCents($settlementBalanceDueCents)
                : null,
            'settlementBalanceDueDecimal' => $balance->hasIssuedInvoice
                ? $this->decimalCents($settlementBalanceDueCents)
                : null,
            'isPaid' => $isPaid,
            // Legacy aliases → settlement when invoice issued (payment / waive / strip)
            'balanceDue' => $balance->hasIssuedInvoice
                ? $this->formatCents($settlementBalanceDueCents)
                : null,
            'balanceDueCents' => $settlementBalanceDueCents,
            'balanceDueDecimal' => $balance->hasIssuedInvoice
                ? $this->decimalCents($settlementBalanceDueCents)
                : null,
            'unappliedDeposits' => $this->formatCents($balance->unappliedDepositsCents),
            'unappliedDepositsCents' => $balance->unappliedDepositsCents,
            'depositsAppliedCents' => $balance->depositsAppliedCents,
            'paymentsAppliedCents' => $balance->paymentsAppliedCents,
            'creditsAppliedCents' => $balance->creditsAppliedCents,
            'financialPosition' => $position->toArray(),
            'customerOwesTodayCents' => $oweTodayCents,
            'projectedBalance' => $position->projectedBalanceLabel(),
            'estimatedDueAtPickupCents' => ! $position->hasIssuedFinalInvoice()
                ? $oweTodayCents
                : null,
            'estimatedDueAtPickup' => ! $position->hasIssuedFinalInvoice() && $position->depositsCents > 0
                ? $position->projectedBalanceLabel()
                : null,
            'storeCreditBalance' => $this->formatCents((int) $repairOrder->customer->store_credit_balance_cents),
            'storeCreditBalanceCents' => (int) $repairOrder->customer->store_credit_balance_cents,
            'canGenerateInvoice' => $this->canGenerateInvoice($repairOrder, $balance, $hasApprovedInvoiceableWork),
            'invoiceBlockingReason' => $this->invoiceBlockingReason($repairOrder, $balance, $hasApprovedInvoiceableWork),
            'canRecordDeposit' => $canRecordDeposit,
            'canRecordManualDeposit' => $canRecordDeposit
                && $this->depositGuard->remainingAllowedDepositCents($repairOrder, $balance, $position) > 0,
            'canRecordPayment' => $this->canRecordPayment($repairOrder, $balance),
            'canClose' => $closeoutBlockingReason === null,
            'closeoutBlockingReason' => $closeoutBlockingReason,
            'canPost' => $canPost,
            'isPosted' => $repairOrder->isPosted(),
            'postedAtLabel' => $repairOrder->posted_at?->timezone(config('app.display_timezone'))->format('M j, Y g:i A'),
            'postBlockingReason' => $postBlockingReason,
            'showFinancialRail' => $this->showFinancialRail($repairOrder, $balance),
            'financialRailReadOnly' => $repairOrder->isTerminal() && $balance->hasIssuedInvoice,
            'ledgerEntries' => $ledgerEntries,
            'hasStoreCreditIssuance' => $ledgerEntries->contains(
                fn (array $entry): bool => $entry['type'] === LedgerEntryType::StoreCreditIssuance->value,
            ),
            'paymentMethods' => [
                PaymentMethod::Cash,
                PaymentMethod::Card,
                PaymentMethod::Check,
            ],
            'square' => $this->cardCapture->publicConfig(),
            'canChargeWithSquare' => $this->canRecordPayment($repairOrder, $balance) && (
                $this->cardCapture->terminalEnabled() || $this->cardCapture->keyedEnabled()
            ),
            'canChargeDepositWithSquare' => $canRecordDeposit
                && $this->depositGuard->remainingAllowedDepositCents($repairOrder, $balance, $position) > 0
                && (
                $this->cardCapture->terminalEnabled() || $this->cardCapture->keyedEnabled()
            ),
            'canManageLedgerEntries' => $this->canManageLedgerEntries($repairOrder),
            'canRecordRefund' => $this->canRecordRefund($repairOrder, $balance),
            'canWaiveBalance' => $this->canWaiveBalance($repairOrder, $balance),
            'waiveDispositionOptions' => collect(RepairOrderCollectionDisposition::waiveOptions())
                ->map(fn (RepairOrderCollectionDisposition $case): array => [
                    'value' => $case->value,
                    'label' => $case->label(),
                ])
                ->values()
                ->all(),
            'canEmailInvoice' => $repairOrder->status->is(RepairOrderStatus::ReadyPickup)
                && $balance->hasIssuedInvoice,
            'invoiceNeedsRefresh' => $invoiceNeedsRefresh,
            'invoiceRefreshBlockedBySettlement' => $invoiceRefreshBlockedBySettlement,
            'canRefreshInvoice' => $canRefreshInvoice,
            'approvedWorkTotal' => $this->formatCents($approvedWorkTotalCents),
            'approvedWorkTotalCents' => $approvedWorkTotalCents,
            'invoiceDriftCents' => $invoiceDriftCents,
            'invoiceDrift' => $invoiceDriftCents !== 0 ? $this->formatCents(abs($invoiceDriftCents)) : null,
            'invoiceRevisionCount' => is_array($invoice?->snapshot_revisions_json)
                ? count($invoice->snapshot_revisions_json)
                : 0,
            'invoiceCustomerPresented' => $invoice?->wasPresentedToCustomer() ?? false,
            'defaultDepositEnabled' => $defaultDeposit->enabled,
            'suggestedDepositCents' => $defaultDeposit->totalCents,
            'suggestedDepositPartsCents' => $defaultDeposit->partsCents,
            'suggestedDepositDiagnosticsCents' => $defaultDeposit->diagnosticsCents,
            'suggestedDepositParts' => $defaultDeposit->partsCents > 0
                ? $this->formatCents($defaultDeposit->partsCents)
                : null,
            'suggestedDepositDiagnostics' => $defaultDeposit->diagnosticsCents > 0
                ? $this->formatCents($defaultDeposit->diagnosticsCents)
                : null,
            'suggestedDeposit' => $defaultDeposit->hasAmount()
                ? $this->formatCents($defaultDeposit->totalCents)
                : null,
            'suggestedDepositDecimal' => $defaultDeposit->enabled && $defaultDeposit->hasAmount()
                ? $this->decimalCents($defaultDeposit->totalCents)
                : null,
            'remainingSuggestedDepositCents' => $remainingSuggestedDepositCents,
            'remainingSuggestedDeposit' => $remainingSuggestedDepositCents !== null && $remainingSuggestedDepositCents > 0
                ? $this->formatCents($remainingSuggestedDepositCents)
                : null,
            'remainingSuggestedDepositDecimal' => $remainingSuggestedDepositCents !== null && $remainingSuggestedDepositCents > 0
                ? $this->decimalCents($remainingSuggestedDepositCents)
                : null,
            'remainingCollectableDepositCents' => $canRecordDeposit ? $oweTodayCents : 0,
            'remainingCollectableDeposit' => $canRecordDeposit && $oweTodayCents > 0
                ? $this->formatCents($oweTodayCents)
                : null,
            'remainingCollectableDepositDecimal' => $canRecordDeposit && $oweTodayCents > 0
                ? $this->decimalCents($oweTodayCents)
                : null,
            'suggestedDepositSatisfied' => $suggestedDepositSatisfied,
            'suggestedDepositHint' => $this->suggestedDepositHint($defaultDeposit),
            'suggestedDepositBreakdown' => $this->depositWorkspaceBreakdown($depositWorkspaceLines),
            'canTakePaymentCapture' => $this->canRecordPayment($repairOrder, $balance) || $canRecordDeposit,
            'paymentCaptureReadiness' => app(PaymentCaptureReadinessProjection::class)->current(),
            'paymentCaptureAttempts' => $this->paymentCaptureAttempts($repairOrder),
        ];
    }

    /**
     * @param  list<RepairOrderDefaultDepositLine>  $lines
     * @return list<array{line_id: int, line_kind: string, description: string, category: string, category_label: string, amount: string, amount_cents: int, included_by_default: bool, sell: string, tax: string|null, shop_fee: string|null, composition: string}>
     */
    private function depositWorkspaceBreakdown(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        return collect($lines)
            ->map(fn (RepairOrderDefaultDepositLine $line): array => [
                'line_id' => $line->lineId,
                'line_kind' => $line->category === 'part' ? 'part' : 'labor',
                'description' => $line->description,
                'category' => $line->category,
                'category_label' => $line->categoryLabel,
                'amount_cents' => $line->amountCents,
                'amount' => $this->formatCents($line->amountCents),
                'included_by_default' => $line->includedByDefault,
                'sell' => $this->formatCents($line->sellSubtotalCents),
                'tax' => $line->taxCents > 0 ? $this->formatCents($line->taxCents) : null,
                'shop_fee' => $line->shopFeeCents > 0 ? $this->formatCents($line->shopFeeCents) : null,
                'composition' => $this->depositLineComposition($line),
            ])
            ->values()
            ->all();
    }

    private function depositLineComposition(RepairOrderDefaultDepositLine $line): string
    {
        $segments = [$line->categoryLabel.' sell '.$this->formatCents($line->sellSubtotalCents)];

        if ($line->taxCents > 0) {
            $segments[] = 'tax '.$this->formatCents($line->taxCents);
        }

        if ($line->shopFeeCents > 0) {
            $segments[] = 'shop fee '.$this->formatCents($line->shopFeeCents);
        }

        return implode(' · ', $segments);
    }

    private function suggestedDepositHint(RepairOrderDefaultDepositResult $defaultDeposit): ?string
    {
        if (! $defaultDeposit->enabled || ! $defaultDeposit->hasAmount()) {
            return null;
        }

        $parts = [];
        if ($defaultDeposit->partsCents > 0) {
            $parts[] = 'parts';
        }
        if ($defaultDeposit->diagnosticsCents > 0) {
            $parts[] = 'diagnostics';
        }

        if ($parts === []) {
            return null;
        }

        return 'Shop policy quote from estimate '.implode(' + ', $parts).' — not money collected until you record payment below.';
    }

    private function workflowHint(RepairOrder $repairOrder, BalanceDueResult $balance, string $workflowPosture): string
    {
        if ($repairOrder->isPosted()) {
            if ($this->collectionDisposition($repairOrder)->excludesFromPostedSales()) {
                return $this->collectionDisposition($repairOrder)->label()
                    .' · invoice still shows what this would have cost · not counted in Sales Posted.';
            }

            return 'Posted '.$repairOrder->posted_at?->timezone(config('app.display_timezone'))->format('M j, g:i A').' · counts in Sales Posted reporting.';
        }

        if ($balance->hasIssuedInvoice && $balance->writeOffsCents > 0) {
            $label = $this->collectionDisposition($repairOrder)->label();

            return match ($workflowPosture) {
                'paid_ready_to_close' => $label.' balance waived · ready to close. Invoice total is what this would have cost.',
                'closed' => $label.' · Would have cost '.$this->formatCents($balance->invoiceTotalCents)
                    .' · Collected '.$this->formatCents($balance->depositsAppliedCents + $balance->paymentsAppliedCents)
                    .' · Waived '.$this->formatCents($balance->writeOffsCents).'.',
                default => $label.' write-off on file.',
            };
        }

        return match ($workflowPosture) {
            'pre_invoice' => 'Record deposits anytime. Final invoice issues when the vehicle is ready for pickup.',
            'pre_invoice_with_deposits' => 'Deposits are on file and will apply when the final invoice is issued. The customer can still pay more toward the remaining balance now.',
            'ready_for_final_invoice' => 'Generate the final invoice to collect payment against approved work.',
            'ready_for_final_invoice_with_deposits' => 'Generate the final invoice to apply deposits and collect the remaining balance.',
            'invoice_issued' => 'Collect payment before vehicle release. Or waive the remaining balance as courtesy / trade.',
            'partially_paid' => 'Balance remains due before closeout. Or waive the remaining balance as courtesy / trade.',
            'paid_ready_to_close' => $repairOrder->pickupHandoffLabel(),
            'closed' => 'Repair order closed.',
            default => '',
        };
    }

    private function collectionDisposition(RepairOrder $repairOrder): RepairOrderCollectionDisposition
    {
        return RepairOrderCollectionDisposition::tryFromMixed($repairOrder->collection_disposition);
    }

    private function showFinancialRail(RepairOrder $repairOrder, BalanceDueResult $balance): bool
    {
        if (! $repairOrder->isTerminal()) {
            return true;
        }

        return $balance->hasIssuedInvoice;
    }

    private function canGenerateInvoice(RepairOrder $repairOrder, BalanceDueResult $balance, bool $hasApprovedInvoiceableWork): bool
    {
        return $repairOrder->status->is(RepairOrderStatus::ReadyPickup)
            && ! $balance->hasIssuedInvoice
            && $hasApprovedInvoiceableWork;
    }

    private function invoiceBlockingReason(RepairOrder $repairOrder, BalanceDueResult $balance, bool $hasApprovedInvoiceableWork): ?string
    {
        if (! $repairOrder->status->is(RepairOrderStatus::ReadyPickup) || $balance->hasIssuedInvoice) {
            return null;
        }

        if ($hasApprovedInvoiceableWork) {
            return null;
        }

        return 'Approve at least one concern with billable lines before the final invoice can be issued.';
    }

    private function canRecordDeposit(RepairOrder $repairOrder, BalanceDueResult $balance): bool
    {
        return ! $repairOrder->isTerminal()
            && ! $balance->hasIssuedInvoice;
    }

    private function canRecordPayment(RepairOrder $repairOrder, BalanceDueResult $balance): bool
    {
        return $balance->hasIssuedInvoice
            && $balance->balanceDueCents > 0
            && ! $repairOrder->isTerminal();
    }

    private function canManageLedgerEntries(RepairOrder $repairOrder): bool
    {
        return ! $repairOrder->isTerminal();
    }

    private function canRecordRefund(RepairOrder $repairOrder, BalanceDueResult $balance): bool
    {
        return ! $repairOrder->isTerminal()
            && $balance->hasIssuedInvoice
            && $balance->paymentsAppliedCents > 0;
    }

    private function canWaiveBalance(RepairOrder $repairOrder, BalanceDueResult $balance): bool
    {
        return ! $repairOrder->isTerminal()
            && $balance->hasIssuedInvoice
            && $balance->balanceDueCents > 0;
    }

    private function workflowPosture(RepairOrder $repairOrder, BalanceDueResult $balance): string
    {
        if ($repairOrder->status->is(RepairOrderStatus::Closed)) {
            return 'closed';
        }

        if (! $repairOrder->status->is(RepairOrderStatus::ReadyPickup)) {
            return $balance->unappliedDepositsCents > 0
                ? 'pre_invoice_with_deposits'
                : 'pre_invoice';
        }

        if (! $balance->hasIssuedInvoice) {
            return $balance->unappliedDepositsCents > 0
                ? 'ready_for_final_invoice_with_deposits'
                : 'ready_for_final_invoice';
        }

        if ($balance->balanceDueCents === 0) {
            return 'paid_ready_to_close';
        }

        if ($balance->invoiceStatus === InvoiceStatus::PartiallyPaid) {
            return 'partially_paid';
        }

        return 'invoice_issued';
    }

    private function workflowLabel(string $workflowPosture): string
    {
        return match ($workflowPosture) {
            'pre_invoice' => 'Pre-invoice',
            'pre_invoice_with_deposits' => 'Deposits on file',
            'ready_for_final_invoice' => 'Ready for final invoice',
            'ready_for_final_invoice_with_deposits' => 'Ready for final invoice',
            'invoice_issued' => 'Invoice issued',
            'partially_paid' => 'Partially paid',
            'paid_ready_to_close' => 'Paid / ready to close',
            'closed' => 'Closed',
            default => 'Financial posture',
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function ledgerHistory(RepairOrder $repairOrder): Collection
    {
        return RepairOrderLedgerEntry::query()
            ->where('repair_order_id', $repairOrder->id)
            ->active()
            ->with('recordedBy:id,name')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (RepairOrderLedgerEntry $entry): array => [
                'id' => $entry->id,
                'type' => $entry->entry_type->value,
                'typeLabel' => $this->ledgerTypeLabel($entry->entry_type),
                'method' => $entry->payment_method?->label(),
                'amount' => $this->formatCents($entry->amount_cents),
                'amountCents' => $entry->amount_cents,
                'reference' => $entry->reference,
                'notes' => $entry->notes,
                'recordedAt' => $entry->recorded_at?->timezone(config('app.display_timezone'))->format('M j, g:i A'),
                'recordedBy' => $entry->recordedBy?->name,
                'isVoided' => $entry->isVoided(),
            ]);
    }

    private function ledgerTypeLabel(LedgerEntryType $type): string
    {
        return match ($type) {
            LedgerEntryType::Deposit => 'Deposit',
            LedgerEntryType::Payment => 'Payment',
            LedgerEntryType::Refund => 'Refund',
            LedgerEntryType::Adjustment => 'Adjustment',
            LedgerEntryType::WriteOff => 'Write-off',
            LedgerEntryType::StoreCreditIssuance => 'Store credit issued',
            LedgerEntryType::StoreCreditApplication => 'Store credit applied',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentCaptureAttempts(RepairOrder $repairOrder): array
    {
        return PaymentCaptureAttempt::query()
            ->where('repair_order_id', $repairOrder->id)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (PaymentCaptureAttempt $attempt): array => [
                'id' => $attempt->id,
                'amount' => $this->formatCents($attempt->amount_cents),
                'statusLabel' => $attempt->status->label(),
                'context' => $attempt->context_kind->value,
                'method' => $attempt->capture_method->value,
                'needsReconciliation' => $attempt->status->isAmbiguous(),
                'isOpen' => $attempt->status->isOpen(),
                'canCancel' => $attempt->status->canCancel(),
                'methodLabel' => $attempt->capture_method === PaymentCaptureMethod::Keyed ? 'Manual' : 'Terminal',
            ])
            ->all();
    }

    private function formatCents(int $cents): string
    {
        return '$'.Money::ofMinor($cents, 'USD')->getAmount()->toScale(2)->__toString();
    }

    private function decimalCents(int $cents): string
    {
        return Money::ofMinor($cents, 'USD')->getAmount()->toScale(2)->__toString();
    }
}
