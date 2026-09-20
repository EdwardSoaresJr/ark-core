<?php

namespace App\Ark\Operations\Messaging;

final class PortalSmsLinkBody
{
    public static function estimate(string $shortUrl): string
    {
        return "Your estimate is ready: {$shortUrl}";
    }

    public static function payment(string $balanceDueDisplay, string $shortUrl): string
    {
        return "Balance due {$balanceDueDisplay}. Pay here: {$shortUrl}";
    }

    public static function deposit(string $amountDisplay, string $shortUrl, bool $remaining = false): string
    {
        if ($remaining) {
            return "Remaining balance {$amountDisplay}. Pay here: {$shortUrl}";
        }

        return "Deposit requested {$amountDisplay}. Pay here: {$shortUrl}";
    }

    public static function inspection(string $shortUrl): string
    {
        return "Your inspection results are ready: {$shortUrl}";
    }
}
