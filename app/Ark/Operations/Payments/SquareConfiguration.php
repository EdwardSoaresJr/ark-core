<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use Square\Environments;
use Square\SquareClient;

final class SquareConfiguration
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public function enabled(): bool
    {
        return (bool) ShopSettings::current()->square_enabled;
    }

    public function configured(): bool
    {
        return $this->credentials->squareConfigured();
    }

    public function operational(): bool
    {
        return $this->enabled() && $this->configured();
    }

    public function applicationId(): string
    {
        return trim((string) ($this->credentials->squareApplicationId() ?? ''));
    }

    public function accessToken(): string
    {
        return trim((string) ($this->credentials->squareAccessToken() ?? ''));
    }

    public function locationId(): string
    {
        return trim((string) ($this->credentials->squareLocationId() ?? ''));
    }

    public function webhookSignatureKey(): string
    {
        return trim((string) ($this->credentials->squareWebhookSignatureKey() ?? ''));
    }

    public function environment(): string
    {
        return $this->credentials->squareEnvironment();
    }

    public function isSandbox(): bool
    {
        return $this->environment() !== 'production';
    }

    public function terminalDeviceId(): ?string
    {
        $deviceId = trim((string) (ShopSettings::current()->square_terminal_device_id ?? ''));

        return $deviceId !== '' ? $deviceId : null;
    }

    public function terminalEnabled(): bool
    {
        return $this->operational()
            && (bool) ShopSettings::current()->square_terminal_enabled
            && $this->terminalDeviceId() !== null;
    }

    public function keyedEnabled(): bool
    {
        return $this->operational()
            && (bool) ShopSettings::current()->square_keyed_enabled;
    }

    public function portalPayEnabled(): bool
    {
        return $this->operational()
            && (bool) ShopSettings::current()->square_portal_pay_enabled;
    }

    public function emailPayEnabled(): bool
    {
        return $this->operational()
            && (bool) ShopSettings::current()->square_email_pay_enabled;
    }

    public function webPaymentsSdkUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox.web.squarecdn.com/v1/square.js'
            : 'https://web.squarecdn.com/v1/square.js';
    }

    public function client(): SquareClient
    {
        return new SquareClient(
            token: $this->accessToken(),
            options: [
                'baseUrl' => $this->isSandbox()
                    ? Environments::Sandbox->value
                    : Environments::Production->value,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function publicConfig(): array
    {
        return [
            'enabled' => $this->operational(),
            'applicationId' => $this->applicationId(),
            'locationId' => $this->locationId(),
            'environment' => $this->environment(),
            'terminalEnabled' => $this->terminalEnabled(),
            'keyedEnabled' => $this->keyedEnabled(),
            'portalPayEnabled' => $this->portalPayEnabled(),
            'emailPayEnabled' => $this->emailPayEnabled(),
            'webPaymentsSdkUrl' => $this->webPaymentsSdkUrl(),
        ];
    }
}
