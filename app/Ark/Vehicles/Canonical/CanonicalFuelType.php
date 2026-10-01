<?php

namespace App\Ark\Vehicles\Canonical;

enum CanonicalFuelType: string
{
    case Gasoline = 'gasoline';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case Electric = 'electric';
    case FlexFuel = 'flex_fuel';

    public function label(): string
    {
        return match ($this) {
            self::Gasoline => 'Gasoline',
            self::Diesel => 'Diesel',
            self::Hybrid => 'Hybrid',
            self::Electric => 'Electric',
            self::FlexFuel => 'Flex Fuel',
        };
    }
}
