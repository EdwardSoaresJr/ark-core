<?php

namespace App\Ark\Operations\Appointments;

/**
 * Week board presentation: by day (legacy columns) or resource allocation lanes.
 */
enum WeekAllocate: string
{
    case Day = 'day';
    case Technician = 'technician';
    case Workstation = 'workstation';

    public static function parse(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Day;
    }

    public function label(): string
    {
        return match ($this) {
            self::Day => 'By day',
            self::Technician => 'Technicians',
            self::Workstation => 'Bays',
        };
    }

    public function isResource(): bool
    {
        return $this !== self::Day;
    }
}
