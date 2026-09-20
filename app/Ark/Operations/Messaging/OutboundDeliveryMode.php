<?php

namespace App\Ark\Operations\Messaging;

enum OutboundDeliveryMode: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Both = 'both';

    public function includesSms(): bool
    {
        return $this === self::Sms || $this === self::Both;
    }

    public function includesEmail(): bool
    {
        return $this === self::Email || $this === self::Both;
    }
}
