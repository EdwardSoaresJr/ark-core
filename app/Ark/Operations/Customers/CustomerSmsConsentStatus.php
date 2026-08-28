<?php

namespace App\Ark\Operations\Customers;

enum CustomerSmsConsentStatus: string
{
    case Subscribed = 'subscribed';
    case OptedOut = 'opted_out';

    public function label(): string
    {
        return match ($this) {
            self::Subscribed => 'Subscribed',
            self::OptedOut => 'Opted out',
        };
    }

    public function allowsOutboundSms(): bool
    {
        return $this === self::Subscribed;
    }
}
