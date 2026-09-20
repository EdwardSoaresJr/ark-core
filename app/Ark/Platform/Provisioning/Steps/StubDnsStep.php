<?php

namespace App\Ark\Platform\Provisioning\Steps;

use App\Ark\Platform\Provisioning\ProvisioningStep;
use App\Ark\Platform\Provisioning\ProvisioningStepResult;
use App\Ark\Platform\ProvisioningRequest;

/** Sprint 1 stub — later DNS adapter. */
final class StubDnsStep implements ProvisioningStep
{
    public function key(): string
    {
        return 'dns';
    }

    public function execute(ProvisioningRequest $request): ProvisioningStepResult
    {
        return ProvisioningStepResult::success();
    }
}
