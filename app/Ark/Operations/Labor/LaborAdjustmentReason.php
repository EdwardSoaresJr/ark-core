<?php

namespace App\Ark\Operations\Labor;

enum LaborAdjustmentReason: string
{
    case Corrosion = 'corrosion';
    case PreviousRepairs = 'previous_repairs';
    case ModifiedVehicle = 'modified_vehicle';
    case HighDisassembly = 'high_disassembly';
    case AccessDifficulty = 'access_difficulty';
    case HeavyDiagnostics = 'heavy_diagnostics';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Corrosion => 'Corrosion',
            self::PreviousRepairs => 'Previous Repairs',
            self::ModifiedVehicle => 'Modified Vehicle',
            self::HighDisassembly => 'High Disassembly',
            self::AccessDifficulty => 'Access Difficulty',
            self::HeavyDiagnostics => 'Heavy Diagnostics',
            self::Custom => 'Custom',
        };
    }
}
