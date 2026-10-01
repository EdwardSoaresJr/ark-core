<?php

namespace App\Ark\Operations\WorkAuthorization;

enum WorkAuthorizationStatus: string
{
    case Authorized = 'authorized';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Authorized => 'Authorized',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Authorized || $this === self::InProgress;
    }
}
