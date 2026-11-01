<?php

namespace App\Ark\Operations\Payments\Capture;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\PlatformConnection;

/**
 * Disposable readiness projection for Take Payment (devices + public SDK config).
 * Never includes provider secrets.
 */
final class PaymentCaptureReadinessProjection
{
    /** @var array<string, mixed>|null */
    private static ?array $memo = null;

    /**
     * @return array{
     *   ready: bool,
     *   status: string,
     *   supports_terminal: bool,
     *   supports_keyed: bool,
     *   supports_portal: bool,
     *   devices: list<array{device_ref: string, label: string, ready: bool}>,
     *   public_config: array{application_id?: string, location_id?: string, environment?: string, web_payments_sdk_url?: string}|null,
     *   message: ?string,
     *   provider: ?string
     * }
     */
    public function current(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $response = app(ArkPaymentsClient::class)->readiness();
        if (($response['reason_code'] ?? null) === 'cloud_unavailable') {
            // A shop without integrated card processing gets no message: recorded payment methods are its normal path.
            $connection = PlatformConnection::current();
            $configured = $connection->isConnected() && filled($connection->credential());

            return self::$memo = [
                'ready' => false,
                'status' => 'cloud_disconnected',
                'supports_terminal' => false,
                'supports_keyed' => false,
                'supports_portal' => false,
                'devices' => [],
                'public_config' => null,
                'message' => $configured ? 'Card processing is temporarily unavailable.' : null,
                'provider' => null,
            ];
        }

        if (($response['unavailable'] ?? false) === true || ($response['ok'] ?? false) !== true) {
            return self::$memo = [
                'ready' => false,
                'status' => (string) ($response['reason_code'] ?? 'cloud_unavailable'),
                'supports_terminal' => false,
                'supports_keyed' => false,
                'supports_portal' => false,
                'devices' => [],
                'public_config' => null,
                'message' => 'Card processing is temporarily unavailable.',
                'provider' => null,
            ];
        }

        $body = $response['body'];
        $names = ShopSettings::current()->paymentReaderNames();
        $devices = is_array($body['available_devices'] ?? null)
            ? $body['available_devices']
            : (is_array($body['devices'] ?? null) ? $body['devices'] : []);
        $public = is_array($body['public_config'] ?? null) ? $body['public_config'] : null;
        $status = (string) ($body['status'] ?? 'disconnected');
        $supportsTerminal = (bool) ($body['supports_terminal'] ?? false);
        $supportsKeyed = (bool) ($body['supports_keyed'] ?? false);

        return self::$memo = [
            'ready' => $status === 'connected' && ($supportsTerminal || $supportsKeyed),
            'status' => $status,
            'supports_terminal' => $supportsTerminal,
            'supports_keyed' => $supportsKeyed,
            'supports_portal' => (bool) ($body['supports_portal'] ?? false),
            'devices' => array_values(array_filter(array_map(static function ($d) use ($names): ?array {
                if (! is_array($d)) {
                    return null;
                }
                $ref = (string) ($d['device_ref'] ?? '');
                if ($ref === '') {
                    return null;
                }

                return [
                    'device_ref' => $ref,
                    'label' => $names[$ref] ?? (string) ($d['label'] ?? $ref),
                    'ready' => (bool) ($d['ready'] ?? true),
                ];
            }, $devices))),
            'public_config' => $public,
            'message' => isset($body['message']) ? (string) $body['message'] : null,
            'provider' => isset($body['provider']) ? (string) $body['provider'] : null,
        ];
    }

    public static function resetMemo(): void
    {
        self::$memo = null;
    }
}
