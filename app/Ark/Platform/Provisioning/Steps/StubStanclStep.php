<?php

namespace App\Ark\Platform\Provisioning\Steps;

use App\Ark\Platform\Provisioning\ProvisioningStep;
use App\Ark\Platform\Provisioning\ProvisioningStepResult;
use App\Ark\Platform\ProvisioningRequest;

/** Sprint 1 stub — Sprint 3 replaces with Stancl tenant create. */
final class StubStanclStep implements ProvisioningStep
{
    public function key(): string
    {
        return 'stancl';
    }

    public function execute(ProvisioningRequest $request): ProvisioningStepResult
    {
        return ProvisioningStepResult::success();
    }
}
