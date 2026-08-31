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

    public function messagingConfigured(): bool
    {
        return app(\App\Ark\Operations\Messaging\OutboundSmsTransport::class)->isConfigured();
    }

    public function twilioConfigured(): bool
    {
        return $this->messagingConfigured();
    }

    public function twilioAccountSid(): ?string
    {
        return null;
    }

    public function twilioAuthToken(): ?string
    {
        return null;
    }

    public function hasStoredTwilioAuthToken(): bool
    {
        return false;
    }

    public function twilioCredentialSource(): string
    {
        return $this->messagingConfigured() ? 'transport' : 'none';
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
        return '';
    }

    public function partsTechCatalogPath(): string
    {
        return '';
    }

    public function partsTechUsername(): ?string
    {
        return null;
    }

    public function partsTechApiKey(): ?string
    {
        return null;
    }

    public function partsTechPassword(): ?string
    {
        return null;
    }

    public function partsTechCatalogConfigured(): bool
    {
        return false;
    }

    public function partsTechQuoteImportConfigured(): bool
    {
        return false;
    }

    public function hasStoredPartsTechApiKey(): bool
    {
        return false;
    }

    public function hasStoredPartsTechPassword(): bool
    {
        return false;
    }

    public function partsTechCredentialSource(): string
    {
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
        return 'whisper-1';
    }

    public function openaiAnalysisModel(): string
    {
        return 'gpt-4o-mini';
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
