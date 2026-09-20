<?php

namespace App\Ark\Operations\Inspections;

enum InspectionItemCategory: string
{
    case Brakes = 'brakes';
    case Tires = 'tires';
    case Battery = 'battery';
    case Fluids = 'fluids';
    case Belts = 'belts';
    case Hoses = 'hoses';
    case Suspension = 'suspension';
    case Steering = 'steering';
    case Lights = 'lights';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Brakes => 'Brakes',
            self::Tires => 'Tires',
            self::Battery => 'Battery',
            self::Fluids => 'Fluids',
            self::Belts => 'Belts',
            self::Hoses => 'Hoses',
            self::Suspension => 'Suspension',
            self::Steering => 'Steering',
            self::Lights => 'Lights',
            self::General => 'General',
        };
    }

    /**
     * @return list<InspectionItemCategory>
     */
    public static function ordered(): array
    {
        return [
            self::Brakes,
            self::Tires,
            self::Battery,
            self::Fluids,
            self::Belts,
            self::Hoses,
            self::Suspension,
            self::Steering,
            self::Lights,
        ];
    }
}
