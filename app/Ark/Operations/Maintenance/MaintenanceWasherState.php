<?php

namespace App\Ark\Operations\Maintenance;

enum MaintenanceWasherState: string
{
    case Installed = 'installed';
    case NotRequired = 'not_required';
    case NotReplaced = 'not_replaced';
    case Include = 'include';

    public function label(): string
    {
        return match ($this) {
            self::Installed => 'Installed',
            self::NotRequired => 'Not Required',
            self::NotReplaced => 'Not Replaced',
            self::Include => 'Drain plug washer',
        };
    }
}
