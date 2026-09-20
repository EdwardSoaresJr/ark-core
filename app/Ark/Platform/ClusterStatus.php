<?php

namespace App\Ark\Platform;

enum ClusterStatus: string
{
    case Provisioning = 'provisioning';
    case Healthy = 'healthy';
    case Maintenance = 'maintenance';
    case Degraded = 'degraded';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Provisioning',
            self::Healthy => 'Healthy',
            self::Maintenance => 'Maintenance',
            self::Degraded => 'Degraded',
            self::Offline => 'Offline',
        };
    }
}
