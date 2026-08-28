<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;

final class WorkstationBrowserRoster
{
    public function remember(WorkstationBrowserBinding $binding, User $user): void
    {
        $ids = $this->operatorIds($binding);

        if (in_array($user->id, $ids, true)) {
            return;
        }

        $ids[] = $user->id;

        $binding->forceFill([
            'known_operator_user_ids' => $ids,
        ])->save();
    }

    public function hasOperator(WorkstationBrowserBinding $binding, User $user): bool
    {
        return in_array($user->id, $this->operatorIds($binding), true);
    }

    /**
     * @return list<int>
     */
    public function operatorIds(WorkstationBrowserBinding $binding): array
    {
        $raw = $binding->known_operator_user_ids;

        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
