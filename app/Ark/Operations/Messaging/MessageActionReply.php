<?php

namespace App\Ark\Operations\Messaging;

enum MessageActionReply: string
{
    case Confirm = 'confirm';
    case Reschedule = 'reschedule';
    case Directions = 'directions';
    case Callback = 'callback';

    public function attentionLabel(): string
    {
        return match ($this) {
            self::Confirm => 'Appointment confirmed',
            self::Reschedule => 'Customer wants to reschedule',
            self::Directions => 'Directions sent',
            self::Callback => 'Customer requested callback',
        };
    }
}
