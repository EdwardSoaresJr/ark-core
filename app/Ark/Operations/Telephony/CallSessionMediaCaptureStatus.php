<?php

namespace App\Ark\Operations\Telephony;

enum CallSessionMediaCaptureStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Available => 'Available',
            self::Failed => 'Failed',
        };
    }

    public function operationalLabel(string $kind = 'Recording'): string
    {
        return match ($this) {
            self::Pending => $kind.' · pending',
            self::Available => $kind.' · available',
            self::Failed => $kind.' · failed',
        };
    }
}
