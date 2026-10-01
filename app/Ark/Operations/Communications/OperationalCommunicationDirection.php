<?php

namespace App\Ark\Operations\Communications;

enum OperationalCommunicationDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'Outbound',
            self::Inbound => 'Inbound',
            self::Internal => 'Internal',
        };
    }

    public function queueLabel(): string
    {
        return match ($this) {
            self::Outbound => 'Outgoing',
            self::Inbound => 'Incoming',
            self::Internal => 'Internal',
        };
    }
}
