<?php

namespace App\Ark\Operations\Telephony;

enum CallSessionDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function queueLabel(): string
    {
        return match ($this) {
            self::Outbound => 'Outgoing',
            self::Inbound => 'Incoming',
        };
    }
}
