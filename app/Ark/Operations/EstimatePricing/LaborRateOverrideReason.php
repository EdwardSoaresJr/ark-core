<?php

namespace App\Ark\Operations\EstimatePricing;

enum LaborRateOverrideReason: string
{
    case CompetitiveMatch = 'competitive_match';
    case MenuOrPackage = 'menu_or_package';
    case CustomerGoodwill = 'customer_goodwill';
    case ManagementApproval = 'management_approval';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CompetitiveMatch => 'Competitive Match',
            self::MenuOrPackage => 'Menu / package price',
            self::CustomerGoodwill => 'Customer Goodwill',
            self::ManagementApproval => 'Management Approval',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
