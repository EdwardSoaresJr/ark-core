<?php

namespace App\Ark\Operations\Messaging\Messenger;

use Illuminate\Support\Facades\Route;

/**
 * Platform Meta App authority — one ARK Communications app per deploy.
 * Shop Page credentials never live here.
 */
final class MetaMessengerPlatformConfiguration
{
    public function __construct(
        private readonly ?string $appId,
        private readonly ?string $appSecret,
        private readonly ?string $verifyToken,
        private readonly string $graphVersion,
        private readonly string $secretSource,
        private readonly string $verifySource,
    ) {}

    public static function current(?LegacyMetaMessengerPlatformCredentialsResolver $legacy = null): self
    {
        $legacy ??= app(LegacyMetaMessengerPlatformCredentialsResolver::class);

        $appId = self::nonEmpty(config('services.meta_messenger.app_id'));
        $envSecret = self::nonEmpty(config('services.meta_messenger.app_secret'));
        $envVerify = self::nonEmpty(config('services.meta_messenger.verify_token'));
        $graphVersion = self::nonEmpty(config('services.meta_messenger.graph_version')) ?? 'v23.0';

        $secret = $envSecret;
        $verify = $envVerify;
        $secretSource = $envSecret !== null ? 'env' : 'missing';
        $verifySource = $envVerify !== null ? 'env' : 'missing';

        if ($secret === null || $verify === null) {
            $legacyCredentials = $legacy->resolve();

            if ($legacyCredentials !== null) {
                if ($secret === null && filled($legacyCredentials['app_secret'] ?? null)) {
                    $secret = (string) $legacyCredentials['app_secret'];
                    $secretSource = 'legacy_shop';
                }

                if ($verify === null && filled($legacyCredentials['verify_token'] ?? null)) {
                    $verify = (string) $legacyCredentials['verify_token'];
                    $verifySource = 'legacy_shop';
                }
            }
        }

        return new self(
            appId: $appId,
            appSecret: $secret,
            verifyToken: $verify,
            graphVersion: $graphVersion,
            secretSource: $secretSource,
            verifySource: $verifySource,
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->appSecret) && filled($this->verifyToken);
    }

    public function appId(): ?string
    {
        return $this->appId;
    }

    public function appSecret(): ?string
    {
        return $this->appSecret;
    }

    public function verifyToken(): ?string
    {
        return $this->verifyToken;
    }

    public function graphVersion(): string
    {
        return $this->graphVersion;
    }

    public function secretSource(): string
    {
        return $this->secretSource;
    }

    public function verifySource(): string
    {
        return $this->verifySource;
    }

    public function webhookUrl(): string
    {
        return Route::has('webhooks.communications.meta.messenger')
            ? route('webhooks.communications.meta.messenger')
            : '';
    }

    private static function nonEmpty(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
