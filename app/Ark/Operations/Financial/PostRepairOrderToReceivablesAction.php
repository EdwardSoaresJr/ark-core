<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Marks a finalized invoice as collectible Accounts Receivable after an on-account close.
 * Does not invent payment or change snapshot amounts.
 */
final class PostRepairOrderToReceivablesAction
{
    public const DEFAULT_TERMS_DAYS = 30;

    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
        private readonly OperationalEventRecorder $events,
    ) {}

    public function execute(RepairOrder $repairOrder, ?User $actor = null, ?int $termsDays = null): EstimateDocument
    {
        $projection = $this->balanceDue->projectForRepairOrder($repairOrder);
        $invoice = $projection->invoice;
        $balance = $projection->balance;

        if ($invoice === null || ! $balance->hasIssuedInvoice) {
            throw new RuntimeException('Generate the final invoice before posting to Accounts Receivable.');
        }

        if ($balance->balanceDueCents <= 0) {
            throw new RuntimeException('A zero balance invoice is not posted to Accounts Receivable.');
        }

        $terms = $termsDays ?? $invoice->payment_terms_days ?? self::DEFAULT_TERMS_DAYS;
        $terms = max(0, min(365, (int) $terms));
        $issuedAt = $invoice->issued_at instanceof Carbon
            ? $invoice->issued_at->copy()
            : Carbon::parse($invoice->issued_at ?? now());
        $dueAt = $invoice->due_at instanceof Carbon
            ? $invoice->due_at
            : $issuedAt->copy()->addDays($terms);

        if ($invoice->posted_to_receivables_at === null
            || (int) $invoice->payment_terms_days !== $terms
            || $invoice->due_at === null) {
            $invoice->forceFill([
                'payment_terms_days' => $terms,
                'due_at' => $dueAt,
                'posted_to_receivables_at' => $invoice->posted_to_receivables_at ?? now(),
            ])->save();
        }

        $invoice = $invoice->fresh();

        $this->events->record(
            OperationalEventName::InvoicePostedToReceivables,
            $repairOrder,
            actor: $actor,
            payload: [
                'invoice_id' => $invoice->id,
                'document_number' => $invoice->document_number,
                'balance_due_cents' => $balance->balanceDueCents,
                'due_at' => optional($invoice->due_at)?->toIso8601String(),
                'payment_terms_days' => $invoice->payment_terms_days,
                'posted_to_receivables_at' => optional($invoice->posted_to_receivables_at)?->toIso8601String(),
            ],
        );

        return $invoice;
    }
}
