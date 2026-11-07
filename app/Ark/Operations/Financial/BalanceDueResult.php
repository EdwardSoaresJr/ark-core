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
        public int $overpaidCents = 0,
    ) {}

    public function isPaid(): bool
    {
        return $this->hasIssuedInvoice
            && $this->balanceDueCents === 0;
    }

    /**
     * Settlement freezes the invoice contract. Deposits alone do not -
     * they reserve work before the final bill is settled.
     */
    /**
     * Money collected beyond what the invoice now bills, owed back to the customer as a refund
     * or store credit. Balance due never goes below zero, so this is where it shows.
     */
    public function hasOverpayment(): bool
    {
        return $this->overpaidCents > 0;
    }

    public function collectedCents(): int
    {
        return $this->depositsAppliedCents + $this->paymentsAppliedCents - $this->refundsAppliedCents;
    }

    public function hasSettlementActivity(): bool
    {
        return $this->paymentsAppliedCents > 0
            || $this->writeOffsCents > 0
            || $this->refundsAppliedCents > 0
            || $this->creditsAppliedCents > 0;
    }
}
