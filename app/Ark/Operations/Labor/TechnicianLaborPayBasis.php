<?php

namespace App\Ark\Operations\Labor;

enum TechnicianLaborPayBasis: string
{
    case Hourly = 'hourly';
    case Flag = 'flag';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly (clock)',
            self::Flag => 'Flag / book time',
        };
    }

    public function basePayLabel(): string
    {
        return match ($this) {
            self::Hourly => 'Clock wage / hr',
            self::Flag => 'Flag rate',
        };
    }

    public function basePayHint(): string
    {
        return match ($this) {
            self::Hourly => 'Straight wage for each paid clock hour — before taxes and benefits.',
            self::Flag => 'What the technician earns for completed flagged production.',
        };
    }

    public function usesUtilization(): bool
    {
        // Hourly always; Flag uses utilization only for floor-equivalent production cost.
        return $this === self::Hourly;
    }
}
