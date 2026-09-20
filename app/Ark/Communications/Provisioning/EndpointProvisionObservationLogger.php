<?php

namespace App\Ark\Communications\Provisioning;

use Illuminate\Support\Facades\Log;

/**
 * Structured observations for endpoint provisioning — support tooling, not debug noise.
 */
final class EndpointProvisionObservationLogger
{
    public function logSuccess(
        string $mac,
        RegenerateEndpointConfigurationResult $result,
        float $durationMs,
        ?EndpointProvisionArtifact $artifact = null,
    ): void {
        Log::info('endpoint.provision.request', [
            'mac' => $mac,
            'artifact' => $artifact?->value,
            'gate' => 'PASS',
            'projection' => $result->wasRegenerated ? 'REGENERATED' : 'REUSED',
            'builder' => $result->projection->builder->value,
            'duration_ms' => round($durationMs, 1),
            'communication_device_id' => $result->projection->communication_device_id,
            'fingerprint' => $result->projection->inputs_fingerprint,
        ]);
    }

    public function logArtifact(string $mac, EndpointProvisionArtifact $artifact, float $durationMs): void
    {
        Log::info('endpoint.provision.request', [
            'mac' => $mac,
            'artifact' => $artifact->value,
            'gate' => 'PASS',
            'duration_ms' => round($durationMs, 1),
        ]);
    }

    public function logNotFound(string $mac, float $durationMs, ?EndpointProvisionArtifact $artifact = null): void
    {
        Log::info('endpoint.provision.request', [
            'mac' => $mac,
            'artifact' => $artifact?->value,
            'gate' => 'NOT_FOUND',
            'duration_ms' => round($durationMs, 1),
        ]);
    }

    public function logGateFailure(
        string $mac,
        EndpointProvisionGateFailure $failure,
        float $durationMs,
        ?EndpointProvisionArtifact $artifact = null,
    ): void {
        Log::info('endpoint.provision.request', [
            'mac' => $mac,
            'artifact' => $artifact?->value,
            'gate' => 'FAIL',
            'gate_reason' => $failure->value,
            'duration_ms' => round($durationMs, 1),
        ]);
    }
}
