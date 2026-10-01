<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Mobile\MobileDevice;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyExtension;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Operations\Telephony\TelephonyShopSettings;
use App\Ark\Platform\VoiceTransportConfiguration;
use App\Models\User;

/**
 * Opaque mobile voice registration payload for Flutter sip_ua adapter.
 */
final class MobileVoiceRegistrationProjection
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    /**
     * @return array{
     *     sip_username: string,
     *     sip_domain: string,
     *     sip_uri: string,
     *     wss_uri: string,
     *     display_name: string,
     *     transport: string,
     * }
     */
    public function for(User $user, MobileDevice $device, TelephonyExtension $extension): array
    {
        $registrar = trim((string) config('voice-transport.sip_registrar', ''));

        if ($registrar === '') {
            $registrar = VoiceTransportConfiguration::sipRegistrar();
        }

        $username = (string) $extension->extension;

        return [
            'sip_username' => $username,
            'sip_domain' => $registrar,
            'sip_uri' => 'sip:'.$username.'@'.$registrar,
            'wss_uri' => '',
            'display_name' => $user->name,
            'transport' => 'twilio_client',
        ];
    }

    public function twilioEnabledForShop(): bool
    {
        if (TelephonyShopSettings::primaryProviderForCurrentShop() !== TelephonyProviderType::Twilio) {
            return false;
        }

        return $this->credentials->twilioConfigured();
    }

    /** @deprecated Use twilioEnabledForShop() */
    public function arkVoiceEnabledForShop(): bool
    {
        return $this->twilioEnabledForShop();
    }

    public function registrarConfigured(): bool
    {
        if (trim((string) config('voice-transport.sip_registrar', '')) !== '') {
            return true;
        }

        return VoiceTransportConfiguration::resolveRegistrar() !== null;
    }
}
