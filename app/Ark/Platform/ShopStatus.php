<?php

namespace App\Ark\Platform;

/**
 * Shop lifecycle — see docs/platform/shop-status-authority-v1.md
 */
enum ShopStatus: string
{
    case Prospect = 'prospect';
    case PendingProvision = 'pending_provision';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Suspended = 'suspended';
    case PendingDeletion = 'pending_deletion';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Prospect',
            self::PendingProvision => 'Pending provision',
            self::Provisioning => 'Provisioning',
            self::Active => 'Active',
            self::Maintenance => 'Maintenance',
            self::Suspended => 'Suspended',
            self::PendingDeletion => 'Pending deletion',
            self::Archived => 'Archived',
        };
    }
}
