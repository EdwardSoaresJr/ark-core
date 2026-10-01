<?php

namespace App\Ark\Operations\Financial;

enum LedgerEntryType: string
{
    case Deposit = 'deposit';
    case Payment = 'payment';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case WriteOff = 'write_off';
    case StoreCreditIssuance = 'store_credit';
    case StoreCreditApplication = 'store_credit_application';

    /** Amount reduces balance due on the repair order invoice. */
    public function reducesBalanceDue(): bool
    {
        return match ($this) {
            self::Deposit,
            self::Payment,
            self::WriteOff,
            self::StoreCreditApplication => true,
            default => false,
        };
    }

    /** Amount increases balance due on the repair order invoice. */
    public function increasesBalanceDue(): bool
    {
        return match ($this) {
            self::Refund,
            self::Adjustment => true,
            default => false,
        };
    }

    public function affectsInvoiceBalance(): bool
    {
        return $this->reducesBalanceDue() || $this->increasesBalanceDue();
    }
}
