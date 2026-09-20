<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class SoloShopOperations
{
    public function isSoloOwnerShop(): bool
    {
        return User::role(ArkRole::Advisor->value)->active()->doesntExist();
    }

    public function requiresTechnicianAssignment(): bool
    {
        return ! $this->isSoloOwnerShop();
    }

    /**
     * The single active staff member, when the shop has exactly one — a solo or
     * mobile-solo operator. Derived from authority (who exists), never a stored
     * "default operator" setting that drifts or is forgotten. Returns null the
     * moment a second staff user exists, because then "the individual" is
     * ambiguous and a human must choose.
     */
    public function soleStaffUser(): ?User
    {
        $roleNames = array_map(
            static fn (ArkRole $role): string => $role->value,
            ArkRole::staffAssignable(),
        );

        $staff = User::query()
            ->active()
            ->role($roleNames)
            ->limit(2)
            ->get();

        return $staff->count() === 1 ? $staff->first() : null;
    }

    public function isSingleUserShop(): bool
    {
        return $this->soleStaffUser() !== null;
    }

    /**
     * @return Collection<int, User>
     */
    public function assignableTechnicians(): Collection
    {
        // Single-user shop: the only staff member is the technician, whatever
        // role they hold. Keeps the picker consistent with auto-assignment.
        $sole = $this->soleStaffUser();

        if ($sole !== null) {
            return User::query()
                ->whereKey($sole->getKey())
                ->get(['id', 'name']);
        }

        if ($this->isSoloOwnerShop()) {
            return User::query()
                ->active()
                ->role([ArkRole::Admin->value, ArkRole::Technician->value])
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return User::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->where('name', ArkRole::Technician->value))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function canAssignAsTechnician(?User $user): bool
    {
        if ($user === null) {
            return true;
        }

        if (! $user->isActive()) {
            return false;
        }

        if ($user->hasRole(ArkRole::Technician->value)) {
            return true;
        }

        if ($this->isSoloOwnerShop() && $user->hasRole(ArkRole::Admin->value)) {
            return true;
        }

        // A single-user shop's only staff member is, by definition, the
        // technician — whatever role they hold (e.g. an advisor-only solo).
        $sole = $this->soleStaffUser();

        return $sole !== null && $sole->getKey() === $user->getKey();
    }
}
