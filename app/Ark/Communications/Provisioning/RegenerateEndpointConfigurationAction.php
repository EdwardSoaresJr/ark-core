<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Operations\Telephony\TelephonyExtension;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds and persists the EndpointConfigurationProjection read model.
 */
final class RegenerateEndpointConfigurationAction
{
    public function __construct(
        private readonly EndpointProvisionGate $gate,
        private readonly CommunicationDeviceModelResolver $modelResolver,
        private readonly ProvisionBuilder $provisionBuilder,
        private readonly InvalidateEndpointConfigurationAction $invalidate,
    ) {}

    public function execute(CommunicationDevice $device, bool $force = false): RegenerateEndpointConfigurationResult
    {
        $device->loadMissing(['workstation', 'deviceModel']);

        $gateFailure = $this->gate->evaluate($device);

        if ($gateFailure instanceof EndpointProvisionGateFailure) {
            throw new EndpointProvisionGateException($gateFailure);
        }

        $extension = $this->gate->extensionForDevice($device);

        if (! $extension instanceof TelephonyExtension) {
            throw new RuntimeException('Workstation extension missing during regeneration.');
        }

        $this->syncProviderIdentifier($device, $extension);

        EndpointProvisionPreflight::assertReady($extension);

        $deviceModel = $this->modelResolver->resolveForDevice($device);
        $fingerprint = EndpointConfigurationInputsFingerprint::for($device, $extension, $deviceModel);

        $current = EndpointConfigurationProjection::query()
            ->where('communication_device_id', $device->id)
            ->current()
            ->first();

        if (! $force && $current instanceof EndpointConfigurationProjection && $current->inputs_fingerprint === $fingerprint) {
            return new RegenerateEndpointConfigurationResult($current, false);
        }

        $context = new EndpointProvisionContext($device, $extension, $deviceModel);
        $builderType = $this->provisionBuilder->resolveBuilderType($context);
        $serialized = $this->provisionBuilder->build($context);

        $projection = DB::transaction(function () use ($device, $fingerprint, $builderType, $serialized): EndpointConfigurationProjection {
            $this->invalidate->execute($device);

            return EndpointConfigurationProjection::query()->create([
                'communication_device_id' => $device->id,
                'inputs_fingerprint' => $fingerprint,
                'serialized_config' => $serialized,
                'builder' => $builderType,
                'format' => EndpointProvisionFormat::PolyCfg,
                'generated_at' => now(),
            ]);
        });

        return new RegenerateEndpointConfigurationResult($projection, true);
    }

    private function syncProviderIdentifier(CommunicationDevice $device, TelephonyExtension $extension): void
    {
        $identifier = trim((string) $extension->extension);

        if ($identifier === '' || $device->provider_identifier === $identifier) {
            return;
        }

        $device->provider_identifier = $identifier;
        $device->save();
    }
}
