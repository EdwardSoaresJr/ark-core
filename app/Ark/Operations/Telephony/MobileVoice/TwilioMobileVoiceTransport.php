<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Mobile\MobileDevice;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Telephony\TelephonyOutboundCallerId;
use App\Models\User;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VoiceGrant;

final class TwilioMobileVoiceTransport implements MobileVoiceTransport
{
    public function __construct(
        private readonly MobileVoiceCredentials $credentials,
        private readonly ShopIntegrationCredentials $integrations,
        private readonly TelephonyOutboundCallerId $callerId,
        private readonly MobileVoiceEndpointRegistrar $endpointRegistrar,
    ) {}

    public function transportKey(): string
    {
        return 'twilio';
    }

    public function isReadyFor(User $user, ?MobileDevice $device = null): bool
    {
        return $this->readinessBlockReason($user, $device) === null;
    }

    public function readinessBlockReason(User $user, ?MobileDevice $device = null): ?string
    {
        if (! $this->integrations->twilioConfigured()) {
            return 'Twilio is not configured.';
        }

        if (! $this->credentials->twilioClientConfigured()) {
            return 'Save Twilio Voice API Key and TwiML App in Settings → Communications → Telephony.';
        }

        if ($this->callerId->resolve() === null) {
            return 'Save the shop Twilio number before placing in-app calls.';
        }

        if ($device === null) {
            return 'Register this device with ARK Mobile first.';
        }

        if ($this->endpointRegistrar->resolveForDevice($device) === null) {
            return 'Mobile voice endpoint is not registered for this device.';
        }

        if (! $this->credentials->inboundPushConfiguredForPlatform($device->platform)) {
            return match (strtolower((string) $device->platform)) {
                'ios', 'ipados' => 'Save the Twilio iOS VoIP push credential SID in Settings → Communications → Mobile Push.',
                'android' => 'Save the Twilio FCM credential SID in Settings → Communications → Mobile Push.',
                default => 'Save the Twilio Voice push credential SID for this device platform in Settings → Communications → Mobile Push.',
            };
        }

        return null;
    }

    public function issueSession(User $user, MobileDevice $device): array
    {
        $endpoint = $this->endpointRegistrar->ensureForDevice($device);
        $identity = MobileVoiceIdentity::fromDevice($device);
        $token = $this->buildAccessToken($identity, $device);

        return [
            'transport' => $this->transportKey(),
            'identity' => $identity,
            'access_token' => $token['jwt'],
            'expires_in' => $token['expires_in'],
            'supports_inbound' => $this->credentials->inboundPushConfiguredForPlatform($device->platform),
            'endpoint_id' => $endpoint->id,
        ];
    }

    public function issueConnect(
        User $user,
        MobileDevice $device,
        MobileVoiceConnectIntent $intent,
        string $connectToken,
    ): array {
        $identity = MobileVoiceIdentity::fromDevice($device);
        $token = $this->buildAccessToken($identity, $device);

        return [
            'transport' => $this->transportKey(),
            'identity' => $identity,
            'access_token' => $token['jwt'],
            'connect_token' => $connectToken,
            'customer_e164' => $intent->customerE164,
            'params' => [
                'connect_token' => $connectToken,
            ],
        ];
    }

    /**
     * @return array{jwt: string, expires_in: int}
     */
    private function buildAccessToken(string $identity, MobileDevice $device): array
    {
        $accountSid = (string) $this->credentials->accountSid();
        $apiKeySid = (string) $this->credentials->apiKeySid();
        $apiKeySecret = (string) $this->credentials->apiKeySecret();
        $twimlAppSid = (string) $this->credentials->twimlAppSid();

        $ttl = 3600;
        $accessToken = new AccessToken($accountSid, $apiKeySid, $apiKeySecret, $ttl, $identity);

        $voiceGrant = new VoiceGrant;
        $voiceGrant->setOutgoingApplicationSid($twimlAppSid);
        $voiceGrant->setIncomingAllow(true);

        $pushCredentialSid = $this->pushCredentialSidForDevice($device);
        if ($pushCredentialSid !== null) {
            $voiceGrant->setPushCredentialSid($pushCredentialSid);
        }

        $accessToken->addGrant($voiceGrant);

        return [
            'jwt' => $accessToken->toJWT(),
            'expires_in' => $ttl,
        ];
    }

    private function pushCredentialSidForDevice(MobileDevice $device): ?string
    {
        return $this->credentials->pushCredentialSidForPlatform($device->platform);
    }
}
