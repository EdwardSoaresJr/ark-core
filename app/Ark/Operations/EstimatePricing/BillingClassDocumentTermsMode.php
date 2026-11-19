<?php

namespace App\Ark\Operations\EstimatePricing;

enum BillingClassDocumentTermsMode: string
{
    case ShopDefaults = 'shop_defaults';
    case Append = 'append';
    case Replace = 'replace';

    public function label(): string
    {
        return match ($this) {
            self::ShopDefaults => 'Shop defaults only',
            self::Append => 'Additional terms appended',
            self::Replace => 'Replace selected default terms',
        };
    }

    public static function tryFromMixed(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(trim((string) $value)) ?? self::ShopDefaults;
    }
}
