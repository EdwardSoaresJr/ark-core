<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;

final class MobileVoiceCredentials
{
    public function __construct(
        private readonly ShopSettings $settings,
        private readonly ShopIntegrationCredentials $integrations,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(ShopSettings::current(), ShopIntegrationCredentials::forCurrentShop());
    }

    public function accountSid(): ?string
    {
        return $this->integrations->twilioAccountSid();
    }

    public function apiKeySid(): ?string
    {
        return $this->resolve($this->settings->twilio_api_key_sid, config('services.twilio.api_key_sid'));
    }

    public function apiKeySecret(): ?string
    {
        return $this->resolveSecret($this->settings->twilio_api_key_secret, config('services.twilio.api_key_secret'));
    }

    public function twimlAppSid(): ?string
    {
        return $this->resolve($this->settings->twilio_voice_twiml_app_sid, config('services.twilio.voice_twiml_app_sid'));
    }

    public function fcmCredentialSid(): ?string
    {
        return $this->resolve($this->settings->twilio_fcm_credential_sid, config('services.twilio.fcm_credential_sid'));
    }

    public function apnsVoipCredentialSid(): ?string
    {
        return $this->resolve($this->settings->twilio_apns_voip_credential_sid, config('services.twilio.apns_voip_credential_sid'));
    }

    public function twilioClientConfigured(): bool
    {
        return filled($this->accountSid())
            && filled($this->apiKeySid())
            && filled($this->apiKeySecret())
            && filled($this->twimlAppSid());
    }

    public function supportsInboundPush(): bool
    {
        return filled($this->fcmCredentialSid()) || filled($this->apnsVoipCredentialSid());
    }

    /**
     * Platform-specific Twilio Voice push credential (VoIP APNs or FCM).
     * Fail closed for inbound Client wake when the matching SID is absent.
     */
    public function pushCredentialSidForPlatform(?string $platform): ?string
    {
        return match (strtolower(trim((string) $platform))) {
            'ios', 'ipados' => $this->apnsVoipCredentialSid(),
            'android' => $this->fcmCredentialSid(),
            default => $this->fcmCredentialSid() ?? $this->apnsVoipCredentialSid(),
        };
    }

    public function inboundPushConfiguredForPlatform(?string $platform): bool
    {
        return filled($this->pushCredentialSidForPlatform($platform));
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

    private function resolveSecret(?string $databaseValue, mixed $environmentValue): ?string
    {
        return $this->resolve($databaseValue, $environmentValue);
    }
}
