<?php

namespace App\Ark\Platform\Provisioning\Events;

use App\Ark\Platform\ProvisioningRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ProvisioningStepStarted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public ProvisioningRequest $request,
        public string $stepKey,
    ) {}
}
