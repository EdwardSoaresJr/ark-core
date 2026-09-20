<?php

namespace App\Ark\Operations\Recommendations;

enum RecommendationLifecycle: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
