<?php

namespace App\Ark\Mobile\Push;

use App\Ark\Operations\Settings\ShopSettings;

readonly class MobilePushSettings
{
    public function __construct(
        public bool $enabled = false,
        public ?string $firebaseProjectId = null,
        public bool $hasStoredCredentials = false,
    ) {}

    public static function fromShopSettings(ShopSettings $settings): self
    {
        $raw = is_array($settings->mobile_push) ? $settings->mobile_push : [];

        $shopEnabled = array_key_exists('enabled', $raw)
            ? filter_var($raw['enabled'], FILTER_VALIDATE_BOOL)
            : null;

        return new self(
            enabled: $shopEnabled ?? filter_var(config('mobile.push.enabled', false), FILTER_VALIDATE_BOOL),
            firebaseProjectId: filled($raw['firebase_project_id'] ?? null)
                ? trim((string) $raw['firebase_project_id'])
                : null,
            hasStoredCredentials: filled($settings->mobile_push_firebase_service_account),
        );
    }

    public static function current(): self
    {
        return self::fromShopSettings(ShopSettings::current());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'firebase_project_id' => $this->resolvedProjectId(),
        ];
    }

    public function resolvedProjectId(): ?string
    {
        if (filled($this->firebaseProjectId)) {
            return $this->firebaseProjectId;
        }

        $configured = trim((string) config('mobile.push.project_id', ''));

        if ($configured !== '') {
            return $configured;
        }

        $credentials = $this->credentialsArray();

        if (is_array($credentials) && filled($credentials['project_id'] ?? null)) {
            return trim((string) $credentials['project_id']);
        }

        return null;
    }

    public function isOperational(): bool
    {
        return $this->enabled
            && filled($this->resolvedProjectId())
            && $this->credentialsArray() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function credentialsArray(): ?array
    {
        $path = (string) config('mobile.push.credentials_path', '');

        if ($path !== '' && is_readable($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $settings = ShopSettings::current();

        if (filled($settings->mobile_push_firebase_service_account)) {
            $decoded = json_decode((string) $settings->mobile_push_firebase_service_account, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    public function credentialsSourceLabel(): string
    {
        $path = (string) config('mobile.push.credentials_path', '');

        if ($path !== '' && is_readable($path)) {
            return 'Platform server file';
        }

        if ($this->hasStoredCredentials) {
            return 'Legacy shop settings (migrate to server file)';
        }

        return 'Not configured on server';
    }

    public function transportSummary(): string
    {
        return 'One Firebase project for the ARK Staff app — shared across all shops on this runtime.';
    }
}
