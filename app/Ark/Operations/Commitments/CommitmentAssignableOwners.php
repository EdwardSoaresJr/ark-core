<?php

namespace App\Ark\Operations\Commitments;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class CommitmentAssignableOwners
{
    /**
     * @return Collection<int, User>
     */
    public function all(): Collection
    {
        return User::query()
            ->active()
            ->role([ArkRole::Admin->value, ArkRole::Advisor->value])
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
