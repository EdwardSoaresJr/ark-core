<?php

namespace App\Ark\Operations\Learn;

use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LearnArkTeamProgressController
{
    public function __invoke(Request $request, LearnArkProgressResolver $resolver): View
    {
        $staffRoleNames = [
            ArkRole::Admin->value,
            ArkRole::Advisor->value,
            ArkRole::Technician->value,
        ];

        $staff = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $staffRoleNames))
            ->orderBy('name')
            ->get();

        $rows = $staff->map(function (User $user) use ($resolver): array {
            $progress = $resolver->requiredProgressFor($user);
            $summary = $resolver->summaryFor($user);
            $highestRole = LearnArkSection::highestStaffRole($user);

            return [
                'user' => $user,
                'role_label' => $highestRole?->label() ?? 'Staff',
                'summary' => $summary,
                'progress' => $progress,
                'current' => $resolver->isCurrent($user),
                'snooze' => $resolver->snoozeState($user),
            ];
        });

        return view('operations.learn.team-progress', [
            'rows' => $rows,
            'trainingGateEnabled' => LearnArkTrainingGate::isShopEnabled(),
        ]);
    }
}
