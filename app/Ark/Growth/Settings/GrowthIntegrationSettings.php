<?php

namespace App\Ark\Growth\Settings;

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\Settings\ShopSettings;

final class GrowthIntegrationSettings
{
    public function __construct(
        private readonly ShopSettings $settings,
    ) {}

    public static function current(): self
    {
        return new self(ShopSettings::current());
    }

    public function isPublicSitemapEnabled(): bool
    {
        return filter_var(
            $this->integration('public_sitemap')['enabled'] ?? false,
            FILTER_VALIDATE_BOOL,
        );
    }

    public function isGoogleBusinessProfileEnabled(): bool
    {
        return filter_var(
            $this->integration('google_business_profile')['enabled'] ?? false,
            FILTER_VALIDATE_BOOL,
        );
    }

    public function googleBusinessProfileLocation(): ?string
    {
        $location = trim((string) ($this->integration('google_business_profile')['location'] ?? ''));

        return $location !== '' ? $location : null;
    }

    public function isGoogleBusinessProfileConfigured(): bool
    {
        return $this->isGoogleBusinessProfileEnabled()
            && filled($this->googleBusinessProfileLocation())
            && $this->hasGoogleServiceAccountCredentials();
    }

    public function isSearchConsoleEnabled(): bool
    {
        return filter_var(
            $this->integration('google_search_console')['enabled'] ?? false,
            FILTER_VALIDATE_BOOL,
        );
    }

    public function searchConsoleProperty(): ?string
    {
        $property = trim((string) ($this->integration('google_search_console')['property'] ?? ''));

        if ($property !== '') {
            return $property;
        }

        $host = parse_url(PublicMarketingUrl::baseUrl(), PHP_URL_HOST);

        return $host ? 'sc-domain:'.$host : null;
    }

    public function isSearchConsoleConfigured(): bool
    {
        return $this->isSearchConsoleEnabled()
            && filled($this->searchConsoleProperty())
            && $this->hasGoogleServiceAccountCredentials();
    }

    public function isGoogleIndexingEnabled(): bool
    {
        return filter_var(
            $this->integration('google_indexing')['enabled'] ?? config('growth.seo_automation.google_indexing_enabled', false),
            FILTER_VALIDATE_BOOL,
        );
    }

    public function isGoogleIndexingConfigured(): bool
    {
        return $this->isGoogleIndexingEnabled() && $this->hasGoogleServiceAccountCredentials();
    }

    public function isSeoAutomationEnabled(): bool
    {
        return filter_var(
            $this->integration('seo_automation')['enabled'] ?? config('growth.seo_automation.auto_publish_enabled', true),
            FILTER_VALIDATE_BOOL,
        );
    }

    public function indexNowKey(): ?string
    {
        $key = trim((string) ($this->integration('indexnow')['key'] ?? ''));

        return $key !== '' ? $key : null;
    }

    public function persistIndexNowKey(string $key): void
    {
        $integrations = is_array($this->settings->growth_integrations) ? $this->settings->growth_integrations : [];
        $integrations['indexnow'] = ['key' => $key];

        $this->settings->update(['growth_integrations' => $integrations]);
    }

    public function hasGoogleServiceAccountCredentials(): bool
    {
        return $this->googleServiceAccountCredentials() !== null;
    }

    public function hasGrowthGoogleServiceAccountOverride(): bool
    {
        return filled($this->settings->growth_google_service_account);
    }

    public function serverGoogleServiceAccountClientEmail(): ?string
    {
        $credentials = $this->serverGoogleServiceAccountCredentials();

        if ($credentials === null) {
            return null;
        }

        $email = trim((string) ($credentials['client_email'] ?? ''));

        return $email !== '' ? $email : null;
    }

    public function googleServiceAccountSource(): ?string
    {
        if (filled($this->settings->growth_google_service_account)) {
            return 'growth_settings';
        }

        if (filled($this->settings->mobile_push_firebase_service_account)) {
            return 'mobile_push_settings';
        }

        if ($this->serverGoogleServiceAccountCredentials() !== null) {
            return 'server_file';
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function googleServiceAccountCredentials(): ?array
    {
        foreach ($this->serviceAccountSources() as $raw) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)
                && filled($decoded['client_email'] ?? null)
                && filled($decoded['private_key'] ?? null)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function serviceAccountSources(): array
    {
        $sources = [];

        if (filled($this->settings->growth_google_service_account)) {
            $sources[] = (string) $this->settings->growth_google_service_account;
        }

        if (filled($this->settings->mobile_push_firebase_service_account)) {
            $sources[] = (string) $this->settings->mobile_push_firebase_service_account;
        }

        $serverCredentials = $this->serverGoogleServiceAccountCredentials();
        if ($serverCredentials !== null) {
            $sources[] = json_encode($serverCredentials, JSON_THROW_ON_ERROR);
        }

        return $sources;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serverGoogleServiceAccountCredentials(): ?array
    {
        $path = $this->serverServiceAccountPath();
        if (! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)
            || ! filled($decoded['client_email'] ?? null)
            || ! filled($decoded['private_key'] ?? null)) {
            return null;
        }

        return $decoded;
    }

    private function serverServiceAccountPath(): string
    {
        return storage_path('app/private/firebase-mobile-service-account.json');
    }

    public function googleBusinessProfileLocationResource(): ?string
    {
        $location = $this->googleBusinessProfileLocation();

        if ($location === null) {
            return null;
        }

        if (str_starts_with($location, 'locations/')) {
            return $location;
        }

        return 'locations/'.$location;
    }

    /**
     * @return array<string, mixed>
     */
    private function integration(string $key): array
    {
        $integrations = $this->settings->growth_integrations;

        if (! is_array($integrations)) {
            return [];
        }

        $integration = $integrations[$key] ?? [];

        return is_array($integration) ? $integration : [];
    }
}
