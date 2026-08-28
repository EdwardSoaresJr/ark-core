<?php

namespace App\Ark\Operations\Payments;

enum PaymentGatewayAttemptStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Canceled = 'canceled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Canceled], true);
    }
}
