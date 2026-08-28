<?php

namespace App\Ark\Operations\Labor;

enum LaborAdjustment: string
{
    case Normal = 'normal';
    case Difficult = 'difficult';
    case Severe = 'severe';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Difficult => 'Difficult',
            self::Severe => 'Severe',
            self::Custom => 'Custom',
        };
    }

    public function selectLabel(): string
    {
        return match ($this) {
            self::Normal => 'Normal (0%)',
            self::Difficult => 'Difficult (+25%)',
            self::Severe => 'Severe (+50%)',
            self::Custom => 'Custom',
        };
    }

    public function defaultFactor(): float
    {
        return match ($this) {
            self::Normal => 1.00,
            self::Difficult => 1.25,
            self::Severe => 1.50,
            self::Custom => 1.00,
        };
    }

    public function requiresReason(): bool
    {
        return $this !== self::Normal;
    }

    public function displayLabel(?float $factor = null): string
    {
        $resolvedFactor = $factor ?? $this->defaultFactor();

        return match ($this) {
            self::Normal => 'Normal',
            self::Difficult => sprintf('Difficult (+%d%%)', (int) round(($resolvedFactor - 1) * 100)),
            self::Severe => sprintf('Severe (+%d%%)', (int) round(($resolvedFactor - 1) * 100)),
            self::Custom => sprintf('Custom (×%.2f)', $resolvedFactor),
        };
    }
}
