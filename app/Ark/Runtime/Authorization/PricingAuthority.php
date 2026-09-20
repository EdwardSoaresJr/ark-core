<?php

namespace App\Ark\Runtime\Authorization;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class PricingAuthority
{
    public static function allows(?Authenticatable $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->can(ArkCapability::PricingOverride->value)
            || $user->can(ArkCapability::SettingsManage->value);
    }
}
