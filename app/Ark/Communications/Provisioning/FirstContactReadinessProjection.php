<?php

namespace App\Ark\Communications\Provisioning;

use App\Ark\Operations\Communications\CommunicationDevice;
use App\Ark\Platform\VoiceTransportConfiguration;

/**
 * Operator-facing readiness for Milestone 1 — First Contact (G4/G5).
 */
final class FirstContactReadinessProjection
{
    public function __construct(
        private readonly EndpointProvisionGate $gate = new EndpointProvisionGate,
    ) {}

    /**
     * @return array{
     *     checks: list<array{label: string, passed: bool}>,
     *     ready: bool,
     *     action_label: string,
     *     action_url: ?string,
     * }
     */
    public function forDevice(CommunicationDevice $device): array
    {
        $device->loadMissing(['workstation', 'deviceModel']);

        $hasMac = filled($device->mac_address);
        $hasModel = $device->communication_device_model_id !== null || filled($device->model);
        $hasWorkstation = $device->workstation_id !== null;
        $hasExtension = $this->gate->extensionForDevice($device) !== null;
        $gateClear = $this->gate->evaluate($device) === null;
        $hostConfigured = VoiceTransportConfiguration::resolveRegistrar() !== null
            || filled(EndpointProvisionServerUrl::base());

        $checks = [
            ['label' => 'Device has MAC', 'passed' => $hasMac],
            ['label' => 'Device model assigned', 'passed' => $hasModel],
            ['label' => 'Workstation assigned', 'passed' => $hasWorkstation],
            ['label' => 'Extension assigned', 'passed' => $hasExtension],
            ['label' => 'Provisioning ready', 'passed' => $gateClear && $hostConfigured],
        ];

        $ready = collect($checks)->every(fn (array $row): bool => $row['passed']);

        return [
            'checks' => $checks,
            'ready' => $ready,
            'action_label' => $ready ? 'Begin First Contact' : 'Complete Setup',
            'action_url' => $ready
                ? route('operations.shop.devices.show', $device).'#certification'
                : $this->setupUrl($device, $hasMac, $hasModel, $hasWorkstation, $hasExtension),
        ];
    }

    private function setupUrl(
        CommunicationDevice $device,
        bool $hasMac,
        bool $hasModel,
        bool $hasWorkstation,
        bool $hasExtension,
    ): ?string {
        if (! $hasWorkstation) {
            return route('operations.shop.communications').'#devices';
        }

        if (! $hasExtension && $device->workstation_id !== null) {
            return route('operations.shop.communications');
        }

        if (! $hasMac || ! $hasModel) {
            return route('operations.shop.devices.show', $device).'#first-contact';
        }

        return route('operations.shop.devices.show', $device).'#first-contact';
    }
}
