<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;

final class AssignWorkstationOperatorAction
{
    public function __construct(
        private readonly WorkstationBrowserRoster $roster,
    ) {}

    public function execute(
        Workstation $workstation,
        User $operator,
        ?WorkstationBrowserBinding $binding = null,
    ): Workstation {
        abort_unless($workstation->is_active, 422, 'This workstation is inactive.');
        abort_unless($operator->isActive(), 422, 'This operator is inactive.');

        $previousOperatorId = $workstation->current_operator_user_id;

        $workstation->forceFill([
            'current_operator_user_id' => $operator->id,
            'last_operator_user_id' => $previousOperatorId !== $operator->id
                ? $previousOperatorId
                : $workstation->last_operator_user_id,
            'last_activity_at' => now(),
        ])->save();

        if ($binding instanceof WorkstationBrowserBinding) {
            $this->roster->remember($binding, $operator);
        }

        return $workstation->fresh(['currentOperator']);
    }
}
