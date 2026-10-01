<?php

namespace App\Ark\Communications\Provisioning;

enum EndpointProvisionGateFailure: string
{
    case Inactive = 'inactive';
    case UnassignedWorkstation = 'unassigned_workstation';
    case MissingExtension = 'missing_extension';
    case ExtensionDisabled = 'extension_disabled';
    case Misconfigured = 'misconfigured';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Misconfigured => 503,
            default => 403,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::Inactive => 'Device is not active.',
            self::UnassignedWorkstation => 'Device is not assigned to a workstation.',
            self::MissingExtension => 'Workstation has no extension assigned.',
            self::ExtensionDisabled => 'Workstation extension is disabled.',
            self::Misconfigured => 'Provisioning is not configured on the server.',
        };
    }
}
