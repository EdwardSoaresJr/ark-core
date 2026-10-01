<?php

namespace App\Ark\Operations\Communications;

enum ScheduledOutboundMessageStatus: string
{
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this !== self::Scheduled;
    }
}
