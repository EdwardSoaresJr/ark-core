<?php

namespace App\Ark\Operations\Staff;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OwnerStaffCoachingController
{
    public function __invoke(Request $request, User $user, StaffCoachingLogPresenter $presenter): View
    {
        $roleValues = collect(ArkRole::staffAssignable())
            ->map(fn (ArkRole $role): string => $role->value)
            ->all();

        abort_unless(
            $user->roles()->whereIn('name', $roleValues)->exists(),
            404,
        );

        return view('operations.owner.staff-coaching', [
            'staffMember' => $user,
            'coachingLogs' => $presenter->forStaffMember($user),
            'coachingLogCount' => StaffCoachingLog::query()->where('staff_user_id', $user->id)->count(),
        ]);
    }
}
