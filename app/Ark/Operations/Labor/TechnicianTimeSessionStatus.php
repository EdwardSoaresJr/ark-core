<?php

namespace App\Ark\Operations\Labor;

enum TechnicianTimeSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case NeedsResolution = 'needs_resolution';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Clocked in',
            self::Closed => 'Closed',
            self::NeedsResolution => 'Needs resolution',
            self::Deleted => 'Deleted',
        };
    }
}
