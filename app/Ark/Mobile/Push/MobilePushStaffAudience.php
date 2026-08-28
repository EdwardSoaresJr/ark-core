<?php

namespace App\Ark\Mobile\Push;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Active shop staff who receive operational continuity pushes on mobile.
 */
final class MobilePushStaffAudience
{
    /**
     * @return list<User>
     */
    public function advisorsAndAdmins(): array
    {
        return User::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                ArkRole::Admin->value,
                ArkRole::Advisor->value,
            ]))
            ->get()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    public function advisorsAndAdminsCollection(): Collection
    {
        return collect($this->advisorsAndAdmins());
    }
}
