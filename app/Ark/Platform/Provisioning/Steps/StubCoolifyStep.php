<?php

namespace App\Ark\Platform\Provisioning\Steps;

use App\Ark\Platform\Provisioning\ProvisioningStep;
use App\Ark\Platform\Provisioning\ProvisioningStepResult;
use App\Ark\Platform\ProvisioningRequest;

/** Retired from default path — use CoolifyAdapter. Kept for isolated tests. */
final class StubCoolifyStep implements ProvisioningStep
{
    public function key(): string
    {
        return 'coolify';
    }

    public function execute(ProvisioningRequest $request): ProvisioningStepResult
    {
        return ProvisioningStepResult::success();
    }
}
