<?php

namespace App\Ark\Platform\Provisioning\Coolify;

final readonly class CoolifyDeploymentStatus
{
    public function __construct(
        public string $deploymentReference,
        public string $status,
    ) {}

    public function isSuccessful(): bool
    {
        return in_array(strtolower($this->status), ['finished', 'success', 'successful', 'completed'], true);
    }

    public function isFailed(): bool
    {
        return in_array(strtolower($this->status), ['failed', 'error', 'cancelled', 'canceled'], true);
    }

    public function isTerminal(): bool
    {
        return $this->isSuccessful() || $this->isFailed();
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }
}
