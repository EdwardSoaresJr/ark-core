<?php

namespace App\Ark\Platform\Provisioning\Coolify;

use Illuminate\Support\Facades\Cache;

/**
 * Adapter-owned execution artifacts (deployment UUIDs, poll refs) for idempotency.
 * Workflow state stays on ProvisioningRequest / orchestrator — not here.
 */
final class CoolifyExecutionStore
{
    public function deploymentReference(int $provisioningRequestId): ?string
    {
        $value = Cache::get($this->key($provisioningRequestId));

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function rememberDeploymentReference(int $provisioningRequestId, string $deploymentReference): void
    {
        Cache::forever($this->key($provisioningRequestId), $deploymentReference);
    }

    public function forget(int $provisioningRequestId): void
    {
        Cache::forget($this->key($provisioningRequestId));
    }

    private function key(int $provisioningRequestId): string
    {
        return 'ark-platform.coolify.deployment-ref.'.$provisioningRequestId;
    }
}
