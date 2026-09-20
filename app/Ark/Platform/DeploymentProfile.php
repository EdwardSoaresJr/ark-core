<?php

namespace App\Ark\Platform;

/**
 * Infrastructure intent only — Shared | Dedicated.
 * Enterprise is a Subscription plan, not a deployment profile.
 *
 * @see docs/platform/cluster-assignment-authority-v1.md
 */
enum DeploymentProfile: string
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
