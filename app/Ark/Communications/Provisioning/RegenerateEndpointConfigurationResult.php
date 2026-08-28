<?php

namespace App\Ark\Communications\Provisioning;

final readonly class RegenerateEndpointConfigurationResult
{
    public function __construct(
        public EndpointConfigurationProjection $projection,
        public bool $wasRegenerated,
    ) {}
}
