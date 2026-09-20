<?php

namespace App\Ark\Operations\Financial;

final readonly class BalanceDueResult
{
    public function __construct(
        public bool $hasIssuedInvoice,
        public int $invoiceTotalCents,
        public int $depositsAppliedCents,
        public int $paymentsAppliedCents,
        public int $refundsAppliedCents,
        public int $adjustmentsCents,
        public int $creditsAppliedCents,
        public int $writeOffsCents,
        public int $balanceDueCents,
        public int $unappliedDepositsCents,
        public InvoiceStatus $invoiceStatus,
    ) {}

    public function isPaid(): bool
    {
        return $this->hasIssuedInvoice
            && $this->balanceDueCents === 0;
    }

    /**
     * Settlement freezes the invoice contract. Deposits alone do not —
     * they reserve work before the final bill is settled.
     */
    public function hasSettlementActivity(): bool
    {
        return $this->paymentsAppliedCents > 0
            || $this->writeOffsCents > 0
            || $this->refundsAppliedCents > 0
            || $this->creditsAppliedCents > 0;
    }
}
