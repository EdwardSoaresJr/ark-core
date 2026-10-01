<?php

namespace App\Ark\Operations\ShopExcellence;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

final class OwnerWorkspaceAccess
{
    public static function allows(?User $user): bool
    {
        return $user !== null
            && $user->isActive()
            && $user->hasRole(ArkRole::Admin->value);
    }
}
