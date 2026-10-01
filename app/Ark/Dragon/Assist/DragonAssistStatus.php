<?php

namespace App\Ark\Dragon\Assist;

enum DragonAssistStatus: string
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    public function isEligibleForRedelivery(): bool
    {
        return $this === self::Pending || $this === self::Dispatched;
    }
}
