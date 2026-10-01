<?php

namespace App\Ark\Operations\Today\Surface;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

final class TodayLensResolver
{
    public function resolve(User $user): TodayLens
    {
        if ($user->canAccessOwnerWorkspace()) {
            return TodayLens::Owner;
        }

        if ($user->hasRole(ArkRole::Technician->value) && ! $user->hasAnyRole([ArkRole::Admin->value, ArkRole::Advisor->value])) {
            return TodayLens::Technician;
        }

        return TodayLens::Advisor;
    }
}
