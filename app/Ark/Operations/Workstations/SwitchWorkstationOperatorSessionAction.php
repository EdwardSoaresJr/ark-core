<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Station unlock must become a full session switch — one identity for auth, operator, and permissions.
 */
final class SwitchWorkstationOperatorSessionAction
{
    public function __construct(
        private readonly WorkstationBrowserRoster $roster,
    ) {}

    public function execute(
        Workstation $workstation,
        User $operator,
        WorkstationBrowserBinding $binding,
    ): Workstation {
        Auth::login($operator);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $previousOperatorId = $workstation->current_operator_user_id;

        $workstation->forceFill([
            'current_operator_user_id' => $operator->id,
            'last_operator_user_id' => $previousOperatorId,
            'last_activity_at' => now(),
        ])->save();

        $this->roster->remember($binding, $operator);

        return $workstation->fresh(['currentOperator']);
    }
}
