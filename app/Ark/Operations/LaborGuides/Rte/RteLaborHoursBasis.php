<?php

namespace App\Ark\Operations\LaborGuides\Rte;

enum RteLaborHoursBasis: string
{
    case Lo = 'lo';
    case Avg = 'avg';
    case Hi = 'hi';

    public function label(): string
    {
        return match ($this) {
            self::Lo => 'Lo',
            self::Avg => 'Avg',
            self::Hi => 'Hi',
        };
    }

    public function column(): string
    {
        return match ($this) {
            self::Lo => 'lo_hr',
            self::Avg => 'avg_hr',
            self::Hi => 'hi_hr',
        };
    }

    public static function default(): self
    {
        return self::tryFrom((string) config('rte-labor-guide.default_hours_basis', 'avg')) ?? self::Avg;
    }
}
