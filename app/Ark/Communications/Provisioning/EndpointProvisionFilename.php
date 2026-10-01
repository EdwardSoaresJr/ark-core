<?php

namespace App\Ark\Communications\Provisioning;

use InvalidArgumentException;

/**
 * Parses Poly provisioning HTTP paths into artifacts + MAC identity.
 */
final readonly class EndpointProvisionFilename
{
    public function __construct(
        public EndpointProvisionArtifact $artifact,
        public ?string $normalizedMac,
        public string $macLower,
    ) {}

    public static function fromConfigPath(string $mac): self
    {
        return new self(
            EndpointProvisionArtifact::DeviceConfig,
            CommunicationDeviceMacAddress::normalize($mac),
            strtolower(preg_replace('/[^0-9a-fA-F]/', '', $mac) ?? ''),
        );
    }

    public static function parse(string $filename): ?self
    {
        if ($filename === '000000000000.cfg') {
            return new self(EndpointProvisionArtifact::MasterApplication, null, '000000000000');
        }

        if ($filename === 'sip.cfg') {
            return new self(EndpointProvisionArtifact::SipConfigShell, null, '000000000000');
        }

        if ($filename === '000000000000-directory.xml') {
            return new self(EndpointProvisionArtifact::Directory, null, '000000000000');
        }

        if (preg_match('/^([0-9a-fA-F]{12})\.cfg$/', $filename, $matches) === 1) {
            return self::tryForMac($matches[1], EndpointProvisionArtifact::MasterApplication);
        }

        if (preg_match('/^([0-9a-fA-F]{12})-(phone|web|cloud)\.cfg$/', $filename, $matches) === 1) {
            $artifact = match ($matches[2]) {
                'phone' => EndpointProvisionArtifact::PhoneShell,
                'web' => EndpointProvisionArtifact::WebShell,
                'cloud' => EndpointProvisionArtifact::CloudShell,
            };

            return self::tryForMac($matches[1], $artifact);
        }

        if (preg_match('/^([0-9a-fA-F]{12})-directory\.xml$/', $filename, $matches) === 1) {
            return self::tryForMac($matches[1], EndpointProvisionArtifact::Directory);
        }

        if (preg_match('/^([0-9a-fA-F]{12})-(calls|license)\.(xml|cfg)$/', $filename, $matches) === 1) {
            return self::tryForMac($matches[1], EndpointProvisionArtifact::OptionalMissing);
        }

        if ($filename === '000000000000-license.cfg') {
            return new self(EndpointProvisionArtifact::OptionalMissing, null, '000000000000');
        }

        return null;
    }

    private static function tryForMac(string $mac, EndpointProvisionArtifact $artifact): ?self
    {
        try {
            $normalized = CommunicationDeviceMacAddress::normalize($mac);
        } catch (InvalidArgumentException) {
            return null;
        }

        return new self($artifact, $normalized, strtolower($mac));
    }
}
