<?php

namespace App\Ark\Operations\Settings;

final class ShopIntegrationCredentials
{
    public function __construct(
        private readonly ShopSettings $settings,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(ShopSettings::current());
    }

    public function twilioAccountSid(): ?string
    {
        return $this->resolve($this->settings->twilio_account_sid, config('services.twilio.account_sid'));
    }

    public function twilioAuthToken(): ?string
    {
        return $this->resolve($this->settings->twilio_auth_token, config('services.twilio.auth_token'));
    }

    public function twilioConfigured(): bool
    {
        return filled($this->twilioAccountSid()) && filled($this->twilioAuthToken());
    }

    public function hasStoredTwilioAuthToken(): bool
    {
        return filled($this->settings->twilio_auth_token);
    }

    public function twilioCredentialSource(): string
    {
        if ($this->hasStoredTwilioAuthToken() || filled($this->settings->twilio_account_sid)) {
            return 'database';
        }

        if (filled(config('services.twilio.auth_token')) || filled(config('services.twilio.account_sid'))) {
            return 'env';
        }

        return 'none';
    }

    public function squareApplicationId(): ?string
    {
        return $this->resolve($this->settings->square_application_id, config('services.square.application_id'));
    }

    public function squareAccessToken(): ?string
    {
        return $this->resolve($this->settings->square_access_token, config('services.square.access_token'));
    }

    public function squareLocationId(): ?string
    {
        return $this->resolve($this->settings->square_location_id, config('services.square.location_id'));
    }

    public function squareWebhookSignatureKey(): ?string
    {
        return $this->resolve(
            $this->settings->square_webhook_signature_key,
            config('services.square.webhook_signature_key'),
        );
    }

    public function squareEnvironment(): string
    {
        $database = strtolower(trim((string) ($this->settings->square_environment ?? '')));

        if ($database === 'production') {
            return 'production';
        }

        if ($database === 'sandbox') {
            return 'sandbox';
        }

        return strtolower((string) config('services.square.environment', 'sandbox')) === 'production'
            ? 'production'
            : 'sandbox';
    }

    public function squareConfigured(): bool
    {
        return filled($this->squareApplicationId())
            && filled($this->squareAccessToken())
            && filled($this->squareLocationId());
    }

    public function hasStoredSquareAccessToken(): bool
    {
        return filled($this->settings->square_access_token);
    }

    public function hasStoredSquareWebhookSignatureKey(): bool
    {
        return filled($this->settings->square_webhook_signature_key);
    }

    public function squareCredentialSource(): string
    {
        if (
            filled($this->settings->square_access_token)
            || filled($this->settings->square_application_id)
            || filled($this->settings->square_location_id)
        ) {
            return 'database';
        }

        if (filled(config('services.square.access_token')) || filled(config('services.square.application_id'))) {
            return 'env';
        }

        return 'none';
    }

    public function partsTechBaseUrl(): string
    {
        $resolved = $this->resolve($this->settings->partstech_base_url, config('services.partstech.base_url'));

        return rtrim((string) ($resolved ?: 'https://app.partstech.com'), '/');
    }

    public function partsTechCatalogPath(): string
    {
        return trim((string) ($this->resolve($this->settings->partstech_catalog_path, config('services.partstech.catalog_path')) ?? ''));
    }

    public function partsTechUsername(): ?string
    {
        return $this->resolve($this->settings->partstech_username, config('services.partstech.username'));
    }

    public function partsTechApiKey(): ?string
    {
        return $this->resolve($this->settings->partstech_api_key, config('services.partstech.api_key'));
    }

    public function partsTechPassword(): ?string
    {
        return $this->resolve($this->settings->partstech_password, config('services.partstech.password'));
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
        return filled($this->settings->partstech_api_key);
    }

    public function hasStoredPartsTechPassword(): bool
    {
        return filled($this->settings->partstech_password);
    }

    public function partsTechCredentialSource(): string
    {
        if (
            filled($this->settings->partstech_password)
            || filled($this->settings->partstech_api_key)
            || filled($this->settings->partstech_username)
        ) {
            return 'database';
        }

        if (
            filled(config('services.partstech.password'))
            || filled(config('services.partstech.api_key'))
            || filled(config('services.partstech.username'))
        ) {
            return 'env';
        }

        return 'none';
    }

    public function mailReplyTo(): ?string
    {
        return $this->resolve($this->settings->postmark_reply_to, config('mail.reply_to.address'));
    }

    public function mailReplyToName(): ?string
    {
        return $this->resolve($this->settings->postmark_reply_to_name, config('mail.reply_to.name'));
    }

    public function transactionalEmailConfigured(): bool
    {
        return app(\App\Ark\Mail\OutboundTransactionalMail::class)->isReady();
    }

    public function openaiApiKey(): ?string
    {
        $database = trim((string) ($this->settings->openai_api_key ?? ''));

        return $database !== '' ? $database : null;
    }

    public function openaiConfigured(): bool
    {
        return filled($this->openaiApiKey());
    }

    public function hasStoredOpenaiApiKey(): bool
    {
        return filled($this->settings->openai_api_key);
    }

    public function openaiTranscriptionModel(): string
    {
        $model = trim((string) ($this->settings->openai_transcription_model ?? ''));

        return $model !== '' ? $model : 'whisper-1';
    }

    public function openaiAnalysisModel(): string
    {
        $model = trim((string) ($this->settings->openai_analysis_model ?? ''));

        return $model !== '' ? $model : 'gpt-4o-mini';
    }

    public function openaiCredentialSource(): string
    {
        return $this->hasStoredOpenaiApiKey() ? 'database' : 'none';
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
