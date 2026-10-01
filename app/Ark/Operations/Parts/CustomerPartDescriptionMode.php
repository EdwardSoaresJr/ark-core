<?php

namespace App\Ark\Operations\Parts;

enum CustomerPartDescriptionMode: string
{
    case Cleaned = 'cleaned';
    case Verbatim = 'verbatim';
    case CleanedWithBrand = 'cleaned_with_brand';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Cleaned => 'Cleaned',
            self::Verbatim => 'Verbatim',
            self::CleanedWithBrand => 'Cleaned + Brand',
            self::Manual => 'Manual',
        };
    }

    public function helpText(): string
    {
        return match ($this) {
            self::Cleaned => 'Shows a customer-friendly functional part name while keeping full catalog details internal.',
            self::Verbatim => 'Shows the imported or entered part description as-is.',
            self::CleanedWithBrand => 'Shows the cleaned part name with the manufacturer brand.',
            self::Manual => 'Uses only advisor-entered customer descriptions.',
        };
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom(trim((string) $value)) ?? self::Cleaned;
    }

    /**
     * @return list<self>
     */
    public static function options(): array
    {
        return self::cases();
    }
}
