<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Mobile\MobileDevice;
use App\Models\User;

interface MobileVoiceTransport
{
    public function transportKey(): string;

    public function isReadyFor(User $user, ?MobileDevice $device = null): bool;

    public function readinessBlockReason(User $user, ?MobileDevice $device = null): ?string;

    /**
     * @return array{
     *     transport: string,
     *     identity: string,
     *     access_token: string,
     *     expires_in: int,
     *     supports_inbound: bool,
     * }
     */
    public function issueSession(User $user, MobileDevice $device): array;

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
    ): array;
}
