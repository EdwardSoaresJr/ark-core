<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Communications\Provisioning\Builders\DeviceProvisionBuilder;
use App\Ark\Communications\Provisioning\Builders\PolyProvisionBuilder;
use InvalidArgumentException;

final class ProvisionBuilder
{
    /**
     * @param  iterable<DeviceProvisionBuilder>  $builders
     */
    public function __construct(
        private readonly iterable $builders,
    ) {}

    public function build(EndpointProvisionContext $context): string
    {
        $builderType = $this->resolveBuilderType($context);

        foreach ($this->builders as $builder) {
            if ($builder->builder() === $builderType) {
                return $builder->build($context);
            }
        }

        throw new InvalidArgumentException("No provision builder registered for [{$builderType->value}].");
    }

    public function resolveBuilderType(EndpointProvisionContext $context): EndpointProvisionBuilder
    {
        if ($context->deviceModel instanceof CommunicationDeviceModel) {
            return $context->deviceModel->builder;
        }

        return EndpointProvisionBuilder::Poly;
    }

    public static function default(): self
    {
        return new self([
            new PolyProvisionBuilder,
        ]);
    }
}
