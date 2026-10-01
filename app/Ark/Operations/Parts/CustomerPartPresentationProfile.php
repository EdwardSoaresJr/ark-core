<?php

namespace App\Ark\Operations\Parts;

enum CustomerPartPresentationProfile: string
{
    case Retail = 'retail';
    case Warranty = 'warranty';
    case RepairPal = 'repairpal';
    case Fleet = 'fleet';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Retail',
            self::Warranty => 'Warranty',
            self::RepairPal => 'RepairPal',
            self::Fleet => 'Fleet',
        };
    }

    public function helpText(): string
    {
        return match ($this) {
            self::Retail => 'Repair-focused part names only on customer estimates.',
            self::Warranty => 'Part names plus part number and vendor for audit documentation.',
            self::RepairPal => 'Part names plus part number for program transparency.',
            self::Fleet => 'Repair-focused part names only on customer estimates.',
        };
    }

    public function showsPartNumber(): bool
    {
        return match ($this) {
            self::Warranty, self::RepairPal => true,
            default => false,
        };
    }

    public function showsVendor(): bool
    {
        return $this === self::Warranty;
    }

    public function showsBrand(): bool
    {
        return false;
    }

    public static function defaultForBillingClassName(?string $billingClassName): self
    {
        return match (mb_strtolower(trim((string) $billingClassName))) {
            'warranty' => self::Warranty,
            'repairpal' => self::RepairPal,
            'fleet' => self::Fleet,
            default => self::Retail,
        };
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Retail;
    }
}
