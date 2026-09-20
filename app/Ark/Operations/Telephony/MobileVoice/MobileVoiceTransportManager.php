<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Mobile\MobileDevice;
use App\Models\User;

final class MobileVoiceTransportManager
{
    public function __construct(
        private readonly NotConfiguredMobileVoiceTransport $transport,
    ) {}

    public function current(): MobileVoiceTransport
    {
        return $this->transport;
    }

    public function isInAppReady(User $user, ?MobileDevice $device = null): bool
    {
        return $this->current()->isReadyFor($user, $device);
    }

    public function readinessBlockReason(User $user, ?MobileDevice $device = null): ?string
    {
        return $this->current()->readinessBlockReason($user, $device);
    }

    /**
     * @return array{
     *     transport: string,
     *     identity: string,
     *     access_token: string,
     *     expires_in: int,
     *     supports_inbound: bool,
     * }
     */
    public function issueSession(User $user, MobileDevice $device): array
    {
        return $this->current()->issueSession($user, $device);
    }

    /**
     * @return array{
     *     transport: string,
     *     identity: string,
     *     access_token: string,
     *     connect_token: string,
     *     customer_e164: string,
     *     params: array<string, string>,
     * }
     */
    public function issueConnect(
        User $user,
        MobileDevice $device,
        MobileVoiceConnectIntent $intent,
        string $connectToken,
    ): array {
        return $this->current()->issueConnect($user, $device, $intent, $connectToken);
    }
}
