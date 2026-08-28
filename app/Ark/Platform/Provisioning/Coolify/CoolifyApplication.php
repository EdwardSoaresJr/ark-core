<?php

namespace App\Ark\Platform\Provisioning\Coolify;

final readonly class CoolifyApplication
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $serverUuid = null,
    ) {}
}
