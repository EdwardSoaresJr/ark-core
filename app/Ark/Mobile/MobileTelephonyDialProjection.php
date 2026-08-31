<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceTransportManager;
use App\Ark\Operations\Telephony\TelephonyEndpointMatcher;
use App\Ark\Operations\Telephony\TelephonyOutboundCallerId;
use App\Ark\Operations\Telephony\OutboundVoiceCallControl;
use App\Models\User;

/**
 * Mobile dial posture — Flutter follows dial_method and voice session payloads from ARK.
 */
final class MobileTelephonyDialProjection
{
    public function __construct(
        private readonly ShopIntegrationCredentials $credentials,
        private readonly OutboundVoiceCallControl $twilio,
        private readonly TelephonyEndpointMatcher $endpointMatcher,
        private readonly TelephonyOutboundCallerId $callerId,
        private readonly MobileVoiceTransportManager $voiceTransports,
        private readonly MobileDeviceResolver $deviceResolver,
    ) {}

    public function dialMethodFor(User $user, ?MobileDevice $device = null): string
    {
        $device ??= $this->deviceResolver->resolveLatestForUser($user);

        if ($this->canUseInAppVoice($user, $device)) {
            return 'in_app';
        }

        if ($this->canUseShopCallback($user)) {
            return 'shop_callback';
        }

        return 'native';
    }

    public function canUseInAppVoice(User $user, ?MobileDevice $device = null): bool
    {
        $device ??= $this->deviceResolver->resolveLatestForUser($user);

        return $this->voiceTransports->isInAppReady($user, $device);
    }

    public function canUseShopCallback(User $user): bool
    {
        if (! $this->credentials->twilioConfigured() || ! $this->twilio->configured()) {
            return false;
        }

        if ($this->callerId->resolve() === null) {
            return false;
        }

        return $this->endpointMatcher->canReceiveMobileCallback($user);
    }

    /**
     * @return array{
     *     dial_method: string,
     *     voice: array<string, mixed>,
     * }
     */
    public function shellTelephony(User $user, ?MobileDevice $device = null): array
    {
        $device ??= $this->deviceResolver->resolveLatestForUser($user);
        $dialMethod = $this->dialMethodFor($user, $device);

        return [
            'dial_method' => $dialMethod,
            'voice' => [
                'in_app_ready' => $this->canUseInAppVoice($user, $device),
                'transport' => $this->voiceTransports->current()->transportKey(),
                'fallback' => $this->canUseShopCallback($user) ? 'shop_callback' : 'native',
                'block_reason' => $this->voiceTransports->readinessBlockReason($user, $device),
                'supports_inbound' => $this->canUseInAppVoice($user, $device)
                    && $device !== null
                    && (filled($device->voip_push_token) || filled($device->fcm_token)),
            ],
        ];
    }
}
