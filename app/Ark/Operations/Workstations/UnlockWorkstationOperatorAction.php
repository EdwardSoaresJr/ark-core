<?php

namespace App\Ark\Operations\Workstations;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class UnlockWorkstationOperatorAction
{
    public function __construct(
        private readonly OperatorPinVerifier $pinVerifier,
        private readonly WorkstationOperatorEligibility $eligibility,
        private readonly WorkstationUnlockRateLimiter $rateLimiter,
        private readonly SwitchWorkstationOperatorSessionAction $switchSession,
    ) {}

    public function execute(
        Workstation $workstation,
        User $viewer,
        User $operator,
        string $pin,
        WorkstationBrowserBinding $binding,
    ): Workstation {
        $this->eligibility->assertCanUnlock($viewer, $operator, $binding);
        $this->rateLimiter->assertNotLimited($binding, $operator);

        try {
            $this->pinVerifier->assertValid($operator, $pin);
        } catch (ValidationException $exception) {
            $this->rateLimiter->recordFailure($binding, $operator);

            throw $exception;
        }

        $this->rateLimiter->clear($binding, $operator);

        return $this->switchSession->execute($workstation, $operator, $binding);
    }
}
