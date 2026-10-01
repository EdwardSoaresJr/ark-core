<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class UpdateWorkstationOperatorPinAction
{
    public function __construct(
        private readonly OperatorAccountPasswordVerifier $passwordVerifier,
    ) {}

    public function execute(User $user, string $password, string $pin): User
    {
        if (! $user->hasOperatorPin()) {
            throw ValidationException::withMessages([
                'pin' => 'You do not have a workstation PIN yet. Create one first.',
            ]);
        }

        $this->passwordVerifier->assertValid($user, $password);

        $user->setOperatorPin($pin);

        return $user->fresh();
    }
}
