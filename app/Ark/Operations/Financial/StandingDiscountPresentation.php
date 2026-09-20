<?php

namespace App\Ark\Operations\Financial;

final class StandingDiscountPresentation
{
    public static function label(?string $customerType, int $discountCents): ?string
    {
        if ($discountCents <= 0) {
            return null;
        }

        $billingClass = trim((string) $customerType);

        return ($billingClass !== '' ? $billingClass : 'Standing').' Discount';
    }
}
