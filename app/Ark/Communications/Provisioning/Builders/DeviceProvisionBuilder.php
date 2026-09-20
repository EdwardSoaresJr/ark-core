<?php

namespace App\Ark\Communications\Provisioning\Builders;

use App\Ark\Communications\Provisioning\EndpointProvisionBuilder;
use App\Ark\Communications\Provisioning\EndpointProvisionContext;

interface DeviceProvisionBuilder
{
    public function builder(): EndpointProvisionBuilder;

    public function build(EndpointProvisionContext $context): string;
}
