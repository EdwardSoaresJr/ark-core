<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

enum RepairOrderLineType: string
{
    case Labor = 'labor';
    case Part = 'part';
    case Fee = 'fee';
    case Note = 'note';
    case Sublet = 'sublet';
    /** Customer package price — not ordinary hourly labor (flag hours / ELR / labor GP). */
    case Package = 'package';

    public function label(): string
    {
        return match ($this) {
            self::Labor => 'Labor',
            self::Part => 'Part',
            self::Fee => 'Fee',
            self::Note => 'Note',
            self::Sublet => 'Sublet',
            self::Package => 'Package',
        };
    }

    public function staffLabel(): string
    {
        return match ($this) {
            self::Sublet => 'Sublet (Service)',
            self::Package => 'Package',
            default => $this->label(),
        };
    }

    public function documentLabel(): string
    {
        return match ($this) {
            self::Sublet => 'Service',
            self::Package => 'Package',
            default => $this->label(),
        };
    }

    public function isNote(): bool
    {
        return $this === self::Note;
    }

    public function isPart(): bool
    {
        return $this === self::Part;
    }

    public function isLabor(): bool
    {
        return $this === self::Labor;
    }

    public function isPackage(): bool
    {
        return $this === self::Package;
    }

    /**
     * Financial labor rollup — taxable labor dollars and estimate labor GP.
     * Includes Sublet (vendor service sold as labor-like revenue).
     */
    public function countsTowardLaborRollup(): bool
    {
        return $this === self::Labor || $this === self::Sublet;
    }

    /**
     * Technician production / flag hours — bay work only.
     * Sublets, packages, fees, and parts never count as tech hours.
     */
    public function countsTowardFlagHours(): bool
    {
        return $this === self::Labor;
    }

    public function isTaxableBaseCandidate(): bool
    {
        return in_array($this, [self::Labor, self::Part, self::Sublet, self::Package], true);
    }

    public function isShopFeeEligible(): bool
    {
        return in_array($this, [self::Labor, self::Part, self::Package], true);
    }

    /**
     * @return list<string|Rule>
     */
    public static function quantityRules(Request $request): array
    {
        return [
            Rule::requiredIf(fn () => $request->input('type') !== self::Note->value),
            'nullable',
            'numeric',
            'min:0',
            'max:99999.99',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyInputDefaults(array $data): array
    {
        if ($this->isNote() || $this->isPackage()) {
            $data['quantity'] = $data['quantity'] ?? 1;
        }

        return $data;
    }
}
