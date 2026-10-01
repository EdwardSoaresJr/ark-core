<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class BindWorkstationBrowserAction
{
    public function __construct(
        private readonly AssignWorkstationOperatorAction $assignOperator,
    ) {}

    public function execute(Workstation $workstation, ?User $operator = null): WorkstationBrowserBinding
    {
        abort_unless($workstation->is_active, 422, 'This workstation is inactive.');

        return DB::transaction(function () use ($workstation, $operator): WorkstationBrowserBinding {
            $workstation->browserBindings()->delete();

            $binding = WorkstationBrowserBinding::issueForWorkstation($workstation);

            if ($operator instanceof User) {
                $this->assignOperator->execute($workstation, $operator, $binding);
            }

            return $binding;
        });
    }
}
