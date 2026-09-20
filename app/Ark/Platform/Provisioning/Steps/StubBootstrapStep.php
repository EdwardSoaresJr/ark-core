<?php

namespace App\Ark\Platform\Provisioning\Steps;

use App\Ark\Platform\Provisioning\ProvisioningStep;
use App\Ark\Platform\Provisioning\ProvisioningStepResult;
use App\Ark\Platform\ProvisioningRequest;

/** Sprint 1 stub — Sprint 4 first admin / settings / branding. */
final class StubBootstrapStep implements ProvisioningStep
{
    public function key(): string
    {
        return 'bootstrap';
    }

    public function execute(ProvisioningRequest $request): ProvisioningStepResult
    {
        return ProvisioningStepResult::success();
    }
}
