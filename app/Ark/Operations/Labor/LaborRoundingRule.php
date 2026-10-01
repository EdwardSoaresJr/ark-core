<?php

namespace App\Ark\Operations\Labor;

enum LaborRoundingRule: string
{
    case Exact = 'exact';
    case Tenth = 'tenth';
    case Quarter = 'quarter';
    case Half = 'half';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'No rounding',
            self::Tenth => 'Round up to 0.1 hr',
            self::Quarter => 'Round up to 0.25 hr',
            self::Half => 'Round up to 0.5 hr',
        };
    }

    public function roundHours(float $hours): float
    {
        return match ($this) {
            self::Exact => round($hours, 2),
            default => $this->ceilToIncrement($hours, $this->increment()),
        };
    }

    private function ceilToIncrement(float $hours, float $increment): float
    {
        if ($increment <= 0) {
            return round($hours, 2);
        }

        $steps = (int) ceil(($hours / $increment) - 1e-9);

        return round($steps * $increment, 2);
    }

    private function increment(): float
    {
        return match ($this) {
            self::Tenth => 0.1,
            self::Quarter => 0.25,
            self::Half => 0.5,
            self::Exact => 0.01,
        };
    }
}
