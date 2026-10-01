<?php

namespace App\Ark\Operations\Briefing;

enum BriefingPriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function weight(): int
    {
        return match ($this) {
            self::Critical => 400,
            self::High => 300,
            self::Normal => 200,
            self::Low => 100,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::High => 'High',
            self::Normal => 'Normal',
            self::Low => 'Low',
        };
    }
}
