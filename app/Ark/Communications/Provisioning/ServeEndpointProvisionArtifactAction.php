<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Communications\Provisioning\Builders\PolyPhoneProvDirectoryBuilder;
use App\Ark\Communications\Provisioning\Builders\PolyPhoneProvMasterBuilder;
use App\Ark\Communications\Provisioning\Builders\PolyPhoneProvShellBuilder;

final readonly class ServeEndpointProvisionArtifactResult
{
    public function __construct(
        public string $body,
        public EndpointProvisionArtifact $artifact,
        public ?string $normalizedMac,
        public ?RegenerateEndpointConfigurationResult $projectionResult = null,
    ) {}
}

final class ServeEndpointProvisionArtifactAction
{
    public function __construct(
        private readonly ServeEndpointProvisionAction $serveDeviceConfig,
        private readonly PolyPhoneProvMasterBuilder $masterBuilder,
        private readonly PolyPhoneProvShellBuilder $shellBuilder,
        private readonly PolyPhoneProvDirectoryBuilder $directoryBuilder,
    ) {}

    public function execute(EndpointProvisionFilename $filename): ServeEndpointProvisionArtifactResult
    {
        if ($filename->artifact === EndpointProvisionArtifact::OptionalMissing) {
            return new ServeEndpointProvisionArtifactResult(
                $this->shellBuilder->build(),
                $filename->artifact,
                $filename->normalizedMac,
            );
        }

        if ($filename->artifact->servesEmptyShell()) {
            return new ServeEndpointProvisionArtifactResult(
                $this->shellBuilder->build(),
                $filename->artifact,
                $filename->normalizedMac,
            );
        }

        if ($filename->artifact === EndpointProvisionArtifact::MasterApplication) {
            return new ServeEndpointProvisionArtifactResult(
                $this->masterBuilder->build($filename->macLower),
                $filename->artifact,
                $filename->normalizedMac,
            );
        }

        if ($filename->artifact === EndpointProvisionArtifact::Directory) {
            return new ServeEndpointProvisionArtifactResult(
                $this->directoryBuilder->build(),
                $filename->artifact,
                $filename->normalizedMac,
            );
        }

        if ($filename->artifact === EndpointProvisionArtifact::DeviceConfig) {
            $result = $this->serveDeviceConfig->execute($filename->normalizedMac ?? '');

            return new ServeEndpointProvisionArtifactResult(
                (string) $result->projection->serialized_config,
                $filename->artifact,
                $filename->normalizedMac,
                $result,
            );
        }

        throw new EndpointProvisionNotFoundException;
    }
}
