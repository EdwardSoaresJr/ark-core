<?php

namespace App\Ark\Platform\Provisioning\Coolify;

final readonly class CoolifyDeploymentCommand
{
    public function __construct(
        public string $applicationUuid,
        public string $serverUuid,
        public string $deploymentTarget,
    ) {}
}
