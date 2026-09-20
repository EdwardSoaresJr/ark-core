<?php

namespace App\Ark\Platform;

use RuntimeException;

/**
 * Deployment-owned SIP transport values for desk phone provisioning.
 *
 * Intentionally separate from {@see ShopBaseUrl} — HTTP identity ≠ SIP identity.
 * Desk phones register to Twilio Elastic SIP — not a shop PBX.
 *
 * @see docs/platform/shop-identity-v1.md
 */
final class VoiceTransportConfiguration
{
    public const STORAGE_RELATIVE_PATH = 'app/private/secrets/voice-sip-registrar';

    public static function sipRegistrar(): string
    {
        self::applyRuntimeConfig();

        $host = trim((string) config('voice-transport.sip_registrar', ''));

        if ($host === '') {
            throw new RuntimeException('VOICE_SIP_REGISTRAR is not configured for this deployment.');
        }

        return $host;
    }

    public static function sipPort(): int
    {
        $port = (int) config('voice-transport.sip_port', 5060);

        return $port > 0 ? $port : 5060;
    }

    public static function sipOutboundProxy(): ?string
    {
        $proxy = trim((string) config('voice-transport.sip_outbound_proxy', ''));

        return $proxy !== '' ? $proxy : null;
    }

    public static function resolveRegistrar(): ?string
    {
        foreach ([
            static fn (): string => trim((string) env('VOICE_SIP_REGISTRAR', '')),
            static fn (): ?string => self::readEnvKey(self::sharedSecretsPath(), 'VOICE_SIP_REGISTRAR'),
            static fn (): ?string => self::readFile(self::storagePath()),
        ] as $source) {
            $value = $source();

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    public static function sourceLabel(): string
    {
        if (trim((string) env('VOICE_SIP_REGISTRAR', '')) !== '') {
            return 'Configured in server .env (VOICE_SIP_REGISTRAR)';
        }

        if (self::readEnvKey(self::sharedSecretsPath(), 'VOICE_SIP_REGISTRAR') !== null) {
            return 'Loaded from shared secrets file';
        }

        if (self::readFile(self::storagePath()) !== null) {
            return 'Auto-provisioned on deploy';
        }

        return 'Missing — run ark:voice:ensure-transport-config';
    }

    public static function ensure(): ?string
    {
        $existing = self::resolveRegistrar();

        if ($existing !== null) {
            self::applyRuntimeConfig($existing);

            return $existing;
        }

        return null;
    }

    public static function applyRuntimeConfig(?string $registrar = null): void
    {
        $resolved = trim((string) ($registrar ?? self::resolveRegistrar() ?? ''));

        if ($resolved === '') {
            return;
        }

        config([
            'voice-transport.sip_registrar' => $resolved,
            'telephony.sip_provisioning.host' => $resolved,
        ]);
    }

    public static function storagePath(): string
    {
        return storage_path(self::STORAGE_RELATIVE_PATH);
    }

    public static function sharedSecretsPath(): ?string
    {
        $path = trim((string) env('ARK_SHARED_SECRETS_PATH', '/data/ark-shared/secrets/ark-production.env'));

        return $path !== '' && is_readable($path) ? $path : null;
    }

    private static function readFile(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $value = trim((string) file_get_contents($path));

        return $value !== '' ? $value : null;
    }

    private static function readEnvKey(?string $path, string $key): ?string
    {
        if ($path === null || ! is_readable($path)) {
            return null;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (! str_starts_with($line, $key.'=')) {
                continue;
            }

            $value = trim(substr($line, strlen($key) + 1));

            return $value !== '' ? $value : null;
        }

        return null;
    }
}
