<?php

namespace App\Ark\Platform\Provisioning\Coolify;

final readonly class CoolifyServer
{
    public function __construct(
        public string $uuid,
        public string $name,
    ) {}

    public function matchesTarget(string $deploymentTarget): bool
    {
        $target = strtolower(trim($deploymentTarget));

        return $target !== ''
            && (strtolower($this->uuid) === $target || strtolower($this->name) === $target);
    }
}
