<?php

namespace App\Ark\Platform;

enum ClusterType: string
{
    case Shared = 'shared';
    case Dedicated = 'dedicated';

    public function label(): string
    {
        return match ($this) {
            self::Shared => 'Shared',
            self::Dedicated => 'Dedicated',
        };
    }
}
