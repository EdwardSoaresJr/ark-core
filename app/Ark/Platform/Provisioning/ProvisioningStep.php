<?php

namespace App\Ark\Platform\Provisioning;

use App\Ark\Platform\ProvisioningRequest;

/**
 * Adapter contract — every infrastructure step looks identical.
 *
 * @see docs/platform/orchestrator-rule-v1.md
 */
interface ProvisioningStep
{
    /**
     * Stable key for skip-completed retry (e.g. coolify, stancl).
     */
    public function key(): string;

    public function execute(ProvisioningRequest $request): ProvisioningStepResult;
}
