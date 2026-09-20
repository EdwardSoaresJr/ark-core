<?php

namespace App\Ark\Operations\Workstations;

use App\Ark\Operations\Staff\StaffMemberController;
use App\Models\User;
use Illuminate\Support\Collection;

final class WorkstationOperatorEligibility
{
    public function __construct(
        private readonly WorkstationBrowserRoster $roster,
    ) {}

    public function assertCanUnlock(User $viewer, User $operator, WorkstationBrowserBinding $binding): void
    {
        if (! $operator->isActive()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pin' => 'This staff member is inactive.',
            ]);
        }

        if (! $operator->hasOperatorPin()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pin' => 'This staff member does not have a workstation PIN yet.',
            ]);
        }

        if (! $this->roster->hasOperator($binding, $operator)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pin' => 'This operator has not signed into ARK on this station before.',
            ]);
        }

        // Shared station: PIN + roster prove identity. Hierarchy is not a lock-screen filter.
    }

    /**
     * @return Collection<int, User>
     */
    public function unlockCandidates(User $viewer, ?WorkstationBrowserBinding $binding): Collection
    {
        if ($binding === null) {
            return collect();
        }

        $rosterIds = $this->roster->operatorIds($binding);

        if ($rosterIds === []) {
            return collect();
        }

        return StaffMemberController::list()
            ->filter(fn (User $user): bool => $user->isActive())
            ->filter(fn (User $user): bool => in_array($user->id, $rosterIds, true))
            ->filter(fn (User $user): bool => $user->hasOperatorPin());
    }
}
