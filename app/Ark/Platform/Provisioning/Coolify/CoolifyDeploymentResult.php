<?php

namespace App\Ark\Platform\Provisioning\Coolify;

final readonly class CoolifyDeploymentResult
{
    public function __construct(
        public string $deploymentReference,
        public string $status = 'queued',
    ) {}
}
