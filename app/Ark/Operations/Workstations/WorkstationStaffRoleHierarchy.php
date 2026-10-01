<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;

/**
 * Staff role ordering for station unlock eligibility (peers and below).
 */
final class WorkstationStaffRoleHierarchy
{
    /** @var list<ArkRole> Highest privilege first. */
    private const HIERARCHY = [
        ArkRole::Admin,
        ArkRole::Advisor,
        ArkRole::Technician,
    ];

    public static function highestRole(User $user): ?ArkRole
    {
        foreach (self::HIERARCHY as $role) {
            if ($user->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
    }

    public static function canViewerUnlockAs(User $viewer, User $operator): bool
    {
        $viewerRole = self::highestRole($viewer);
        $operatorRole = self::highestRole($operator);

        if ($viewerRole === null || $operatorRole === null) {
            return false;
        }

        return self::rank($operatorRole) >= self::rank($viewerRole);
    }

    public static function rank(ArkRole $role): int
    {
        $rank = array_search($role, self::HIERARCHY, true);

        return $rank === false ? PHP_INT_MAX : $rank;
    }

    /**
     * @return list<ArkRole>
     */
    public static function roles(): array
    {
        return self::HIERARCHY;
    }
}
