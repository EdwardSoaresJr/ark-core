<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CreateWorkstationOperatorPinAction
{
    public function __construct(
        private readonly UnlockWorkstationOperatorAction $unlock,
        private readonly OperatorAccountPasswordVerifier $passwordVerifier,
        private readonly WorkstationBrowserRoster $roster,
    ) {}

    public function execute(
        User $user,
        string $password,
        string $pin,
        ?Workstation $workstation = null,
        ?WorkstationBrowserBinding $binding = null,
    ): User {
        if ($user->hasOperatorPin()) {
            throw ValidationException::withMessages([
                'pin' => 'Your workstation PIN is already set.',
            ]);
        }

        $this->passwordVerifier->assertValid($user, $password);

        $user->setOperatorPin($pin);

        if (
            $workstation instanceof Workstation
            && $workstation->is_active
            && $binding instanceof WorkstationBrowserBinding
        ) {
            $this->roster->remember($binding, $user->fresh());

            $this->unlock->execute(
                $workstation,
                $user->fresh(),
                $user->fresh(),
                $pin,
                $binding,
            );
        }

        return $user->fresh();
    }
}
