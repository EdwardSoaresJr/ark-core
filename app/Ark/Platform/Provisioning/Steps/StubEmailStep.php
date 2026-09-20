<?php

namespace App\Ark\Platform\Provisioning\Steps;

use App\Ark\Platform\Provisioning\ProvisioningStep;
use App\Ark\Platform\Provisioning\ProvisioningStepResult;
use App\Ark\Platform\ProvisioningRequest;

/** Sprint 1 stub — welcome email later. */
final class StubEmailStep implements ProvisioningStep
{
    public function key(): string
    {
        return 'email';
    }

    public function execute(ProvisioningRequest $request): ProvisioningStepResult
    {
        return ProvisioningStepResult::success();
    }
}
