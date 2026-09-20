<?php

namespace App\Ark\Communications\Provisioning\Builders;

use App\Ark\Communications\Provisioning\EndpointProvisionBuilder;
use App\Ark\Communications\Provisioning\EndpointProvisionContext;

/**
 * Poly device config serialization for EndpointConfigurationProjection.
 */
final class PolyProvisionBuilder implements DeviceProvisionBuilder
{
    public const SERIALIZATION_VERSION = 16;

    public function __construct(
        private readonly PolyPhoneProvDeviceConfigBuilder $deviceConfig = new PolyPhoneProvDeviceConfigBuilder,
    ) {}

    public function builder(): EndpointProvisionBuilder
    {
        return EndpointProvisionBuilder::Poly;
    }

    public function build(EndpointProvisionContext $context): string
    {
        return $this->deviceConfig->build($context);
    }
}
