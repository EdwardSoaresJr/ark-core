<?php

namespace App\Ark\Operations\Customers;

enum CustomerIdentityPressure: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Complete => 'Customer info complete',
            self::Incomplete => 'Customer info incomplete',
            self::Critical => 'Contact info missing',
        };
    }

    public function showsChip(): bool
    {
        return $this !== self::Complete;
    }

    public function chipTone(): string
    {
        return match ($this) {
            self::Critical => 'critical',
            self::Incomplete => 'incomplete',
            default => 'neutral',
        };
    }

    public function visibilityHint(): ?string
    {
        return null;
    }
}
