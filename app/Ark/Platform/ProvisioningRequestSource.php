<?php

namespace App\Ark\Platform;

enum ProvisioningRequestSource: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Manual => 'Manual',
        };
    }
}
