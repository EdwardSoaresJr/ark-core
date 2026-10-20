<?php

namespace App\Ark\Operations\Settings;

use Illuminate\Support\Facades\Schema;

final class ShopIntegrationCredentials
{
    public function __construct(
        private readonly ShopSettings $settings,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(ShopSettings::current());
    }

    public function messagingConfigured(): bool
    {
        return app(\App\Ark\Operations\Messaging\OutboundSmsTransport::class)->isConfigured();
    }

    public function twilioConfigured(): bool
    {
        return filled($this->twilioAccountSid()) && filled($this->twilioAuthToken());
    }

    public function twilioAccountSid(): ?string
    {
        $sid = trim((string) config('services.twilio.account_sid', ''));

        return $sid !== '' ? $sid : null;
    }

    public function twilioAuthToken(): ?string
    {
        $token = trim((string) config('services.twilio.auth_token', ''));

        return $token !== '' ? $token : null;
    }

    public function hasStoredTwilioAuthToken(): bool
    {
        return $this->twilioAuthToken() !== null;
    }

    public function twilioFromNumber(): ?string
    {
        $from = trim((string) config('services.twilio.from', ''));

        if ($from !== '') {
            return $from;
        }

        $inbound = trim((string) ($this->settings->telephony_inbound_number ?? ''));

        return $inbound !== '' ? $inbound : null;
    }

    public function twilioCredentialSource(): string
    {
        return $this->twilioConfigured() ? 'environment' : 'none';
    }

    public function partsTechBaseUrl(): string
    {
        $resolved = $this->resolve($this->settings->partstech_base_url ?? null, config('services.partstech.base_url'));

        return rtrim((string) ($resolved ?: ''), '/');
    }

    public function partsTechCatalogPath(): string
    {
        return trim((string) ($this->resolve($this->settings->partstech_catalog_path ?? null, config('services.partstech.catalog_path')) ?? ''));
    }

    public function partsTechUsername(): ?string
    {
        return $this->resolve($this->settings->partstech_username ?? null, config('services.partstech.username'));
    }

    public function partsTechApiKey(): ?string
    {
        return $this->resolve($this->settings->partstech_api_key ?? null, config('services.partstech.api_key'));
    }

    public function partsTechPassword(): ?string
    {
        return $this->resolve($this->settings->partstech_password ?? null, config('services.partstech.password'));
    }

    public function partsTechCatalogConfigured(): bool
    {
        $hasCredential = filled($this->partsTechApiKey()) || filled($this->partsTechPassword());

        return $this->partsTechBaseUrl() !== ''
            && filled($this->partsTechUsername())
            && $hasCredential;
    }

    public function partsTechQuoteImportConfigured(): bool
    {
        return $this->partsTechBaseUrl() !== ''
            && filled($this->partsTechUsername())
            && filled($this->partsTechPassword());
    }

    public function hasStoredPartsTechApiKey(): bool
    {
        return filled($this->settings->partstech_api_key ?? null);
    }

    public function hasStoredPartsTechPassword(): bool
    {
        return filled($this->settings->partstech_password ?? null);
    }

    public function partsTechCredentialSource(): string
    {
        if (
            filled($this->settings->partstech_password ?? null)
            || filled($this->settings->partstech_api_key ?? null)
            || filled($this->settings->partstech_username ?? null)
        ) {
            return 'database';
        }

        if (
            filled(config('services.partstech.username'))
            || filled(config('services.partstech.password'))
            || filled(config('services.partstech.api_key'))
        ) {
            return 'env';
        }

        return 'none';
    }

    public function repairLinkLaunchUrl(): ?string
    {
        if (! $this->repairLinkEnabled() || ! ShopSettingsSchema::has('repairlink_url')) {
            return null;
        }

        return self::normalizedHttpsUrl($this->settings->repairlink_url ?? null);
    }

    public function repairLinkEnabled(): bool
    {
        if (! ShopSettingsSchema::has('repairlink_enabled')) {
            return false;
        }

        return (bool) $this->settings->repairlink_enabled;
    }

    public function repairLinkConfigured(): bool
    {
        return $this->repairLinkLaunchUrl() !== null;
    }

    public function nexpartLaunchUrl(): ?string
    {
        if (! $this->nexpartEnabled() || ! ShopSettingsSchema::has('nexpart_url')) {
            return null;
        }

        return self::normalizedHttpsUrl($this->settings->nexpart_url ?? null);
    }

    public function nexpartEnabled(): bool
    {
        if (! ShopSettingsSchema::has('nexpart_enabled')) {
            return false;
        }

        return (bool) $this->settings->nexpart_enabled;
    }

    public function nexpartConfigured(): bool
    {
        return $this->nexpartLaunchUrl() !== null;
    }

    public static function normalizedHttpsUrl(?string $value): ?string
    {
        $candidate = trim((string) $value);

        if ($candidate === '') {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $candidate) !== 1) {
            $candidate = 'https://'.$candidate;
        }

        if (! str_starts_with(strtolower($candidate), 'https://')) {
            return null;
        }

        $parts = parse_url($candidate);

        if (! is_array($parts) || ! filled($parts['host'] ?? null)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');

        if ($host === '' || str_contains($host, ' ')) {
            return null;
        }

        $url = rtrim('https://'.$host.$port.$path, '/');

        return $url !== 'https://' ? $url : null;
    }

    public function hasStoredSquareAccessToken(): bool
    {
        return false;
    }

    public function hasStoredSquareWebhookSignatureKey(): bool
    {
        return false;
    }

    public function mailReplyTo(): ?string
    {
        return $this->resolve($this->settings->customerReplyToAddress(), config('mail.reply_to.address'));
    }

    public function mailReplyToName(): ?string
    {
        return $this->resolve($this->settings->customerReplyToName(), config('mail.reply_to.name'));
    }

    public function transactionalEmailConfigured(): bool
    {
        return app(\App\Ark\Mail\OutboundTransactionalMail::class)->isReady();
    }

    public function emailConfigured(): bool
    {
        return $this->transactionalEmailConfigured();
    }

    public function openaiApiKey(): ?string
    {
        return null;
    }

    public function openaiConfigured(): bool
    {
        return false;
    }

    public function hasStoredOpenaiApiKey(): bool
    {
        return false;
    }

    public function openaiTranscriptionModel(): string
    {
        return '';
    }

    public function openaiAnalysisModel(): string
    {
        return '';
    }

    public function openaiCredentialSource(): string
    {
        return 'none';
    }

    public function credentialSourceLabel(string $source): string
    {
        return match ($source) {
            'database' => 'Currently loaded from shop settings.',
            'env' => 'Currently loaded from server environment fallback.',
            default => 'Not configured yet.',
        };
    }

    private function resolve(?string $databaseValue, mixed $environmentValue): ?string
    {
        $database = trim((string) ($databaseValue ?? ''));

        if ($database !== '') {
            return $database;
        }

        $environment = trim((string) ($environmentValue ?? ''));

        return $environment !== '' ? $environment : null;
    }
}
