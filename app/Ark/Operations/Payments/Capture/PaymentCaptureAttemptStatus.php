<?php

namespace App\Ark\Operations\Payments\Capture;

enum PaymentCaptureAttemptStatus: string
{
    case Accepted = 'accepted';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case ReconciliationRequired = 'reconciliation_required';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::Pending => 'Processing',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::ReconciliationRequired => 'Needs reconciliation',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Accepted, self::Pending, self::ReconciliationRequired], true);
    }

    public function canCancel(): bool
    {
        return $this === self::Accepted || $this === self::Pending;
    }

    public function isAmbiguous(): bool
    {
        return $this === self::ReconciliationRequired;
    }

    public function blocksSameAmountCapture(): bool
    {
        return $this->isAmbiguous() || $this === self::Pending || $this === self::Accepted;
    }
}
