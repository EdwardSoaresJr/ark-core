<?php

namespace App\Ark\Operations\Evidence;

enum EvidenceVisibility: string
{
    case Internal = 'internal';
    case Pending = 'pending';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::Pending => 'Pending review',
            self::Shared => 'Shared with customer',
        };
    }

    public function isCustomerFacing(): bool
    {
        return $this === self::Shared;
    }
}
