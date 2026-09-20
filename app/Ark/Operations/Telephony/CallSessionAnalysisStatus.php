<?php

namespace App\Ark\Operations\Telephony;

enum CallSessionAnalysisStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Processing => 'Analyzing',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }
}
