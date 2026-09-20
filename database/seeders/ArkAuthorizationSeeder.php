<?php

namespace Database\Seeders;

use App\Ark\Runtime\Authorization\ArkCapability;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ArkAuthorizationSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect();

        foreach (ArkCapability::cases() as $capability) {
            $permissions->put(
                $capability->value,
                Permission::findOrCreate($capability->value, self::GUARD),
            );
        }

        $rolePermissions = config('ark-permissions.roles');

        foreach (ArkRole::cases() as $arkRole) {
            $role = Role::findOrCreate($arkRole->value, self::GUARD);

            $role->syncPermissions($this->permissionsFor($permissions, $rolePermissions[$arkRole->value] ?? []));
        }

        User::query()
            ->where('email', 'admin@ark.test')
            ->first()
            ?->assignRole(ArkRole::Admin->value);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  Collection<string, Permission>  $permissions
     * @param  array<int, string>  $capabilities
     * @return array<int, Permission>
     */
    private function permissionsFor(Collection $permissions, array $capabilities): array
    {
        return collect($capabilities)
            ->map(fn (string $capability): Permission => $permissions->get($capability))
            ->all();
    }
}
