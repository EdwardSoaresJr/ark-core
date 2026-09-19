<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Platform\Payments\ArkPaymentsClient;
use App\Ark\Platform\Payments\ManagedPaymentsGate;

/**
 * Staff card-present buttons and Web Payments SDK public config.
 * Hosted reads Platform readiness; self-host reads Core Square settings.
 */
final class CardPresentCaptureProjection
{
    /** @var array<string, mixed>|null */
    private ?array $readiness = null;

    public function __construct(
        private readonly ArkPaymentsClient $payments,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function publicConfig(): array
    {
        if (! ManagedPaymentsGate::platformCapture()) {
            return [
                'enabled' => false,
                'applicationId' => '',
                'locationId' => '',
                'environment' => 'sandbox',
                'terminalEnabled' => false,
                'keyedEnabled' => false,
                'portalPayEnabled' => false,
                'emailPayEnabled' => false,
                'webPaymentsSdkUrl' => '',
            ];
        }

        $ready = $this->readiness();
        $public = is_array($ready['public_config'] ?? null) ? $ready['public_config'] : [];
        $connected = ($ready['status'] ?? '') === 'connected';

        return [
            'enabled' => $connected,
            'applicationId' => (string) ($public['application_id'] ?? ''),
            'locationId' => (string) ($public['location_id'] ?? ''),
            'environment' => (string) ($public['environment'] ?? 'sandbox'),
            'terminalEnabled' => $connected && (bool) ($ready['supports_terminal'] ?? false),
            'keyedEnabled' => $connected && (bool) ($ready['supports_keyed'] ?? false),
            'portalPayEnabled' => $connected
                && (bool) ($ready['supports_portal'] ?? false)
                && (bool) ($ready['supports_keyed'] ?? false)
                && filled($public['application_id'] ?? null)
                && filled($public['location_id'] ?? null),
            'emailPayEnabled' => false,
            'webPaymentsSdkUrl' => (string) ($public['web_payments_sdk_url'] ?? ''),
        ];
    }

    public function terminalEnabled(): bool
    {
        return (bool) $this->publicConfig()['terminalEnabled'];
    }

    public function keyedEnabled(): bool
    {
        return (bool) $this->publicConfig()['keyedEnabled'];
    }

    public function portalPayEnabled(): bool
    {
        return (bool) $this->publicConfig()['portalPayEnabled'];
    }

    /**
     * @return list<array{device_ref: string, label: string, ready: bool}>
     */
    public function readyDevices(): array
    {
        if (! ManagedPaymentsGate::platformCapture()) {
            return [];
        }

        $devices = $this->readiness()['available_devices'] ?? [];
        if (! is_array($devices)) {
            return [];
        }

        $out = [];
        foreach ($devices as $device) {
            if (! is_array($device) || ! ($device['ready'] ?? false)) {
                continue;
            }
            $ref = (string) ($device['device_ref'] ?? '');
            if ($ref === '') {
                continue;
            }
            $out[] = [
                'device_ref' => $ref,
                'label' => (string) ($device['label'] ?? $ref),
                'ready' => true,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function readiness(): array
    {
        if ($this->readiness !== null) {
            return $this->readiness;
        }

        $this->readiness = $this->payments->readiness();

        return $this->readiness;
    }
}
