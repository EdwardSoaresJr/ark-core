<?php

namespace App\Ark\Runtime\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Role;

final class DevRolePretend
{
    public static function sessionKey(): string
    {
        return 'dev_role_pretend';
    }

    public static function switcherVisible(?User $user): bool
    {
        return self::authorizedUser($user);
    }

    public static function authorizedUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (! (bool) $user->getAttribute('is_master_admin') && ! app()->environment('local')) {
            return false;
        }

        return User::query()
            ->whereKey($user->id)
            ->whereHas('roles', fn ($query) => $query->where('name', ArkRole::Admin->value))
            ->exists();
    }

    public static function isActive(): bool
    {
        return Session::get(self::sessionKey()) === ArkRole::Technician->value;
    }

    public static function activateTechnician(): void
    {
        Session::put(self::sessionKey(), ArkRole::Technician->value);
    }

    public static function clear(): void
    {
        Session::forget(self::sessionKey());
    }

    public static function applyEffectiveRoles(User $user): void
    {
        if (! self::isActive()) {
            return;
        }

        $role = Role::findByName(ArkRole::Technician->value, 'web');

        $user->setRelation('roles', collect([$role]));
        $user->unsetRelation('permissions');
    }
}
