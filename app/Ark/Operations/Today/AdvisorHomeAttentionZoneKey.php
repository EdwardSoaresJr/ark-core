<?php

namespace App\Ark\Operations\Today;

enum AdvisorHomeAttentionZoneKey: string
{
    case NeedsAction = 'needs_action';
    case ActiveWork = 'active_work';
    case ReadyPickup = 'ready_pickup';

    public function label(): string
    {
        return match ($this) {
            self::NeedsAction => 'Needs Action',
            self::ActiveWork => 'Active Work',
            self::ReadyPickup => 'Ready Pickup',
        };
    }
}
