<?php

namespace App\Ark\Platform\Provisioning\Coolify;

final readonly class CoolifyAuthenticationResult
{
    public function __construct(
        public bool $authenticated,
        public int $teamCount = 0,
    ) {}
}
