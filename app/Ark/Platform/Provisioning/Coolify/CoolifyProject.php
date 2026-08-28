<?php

namespace App\Ark\Platform\Provisioning\Coolify;

final readonly class CoolifyProject
{
    public function __construct(
        public string $uuid,
        public string $name,
    ) {}
}
