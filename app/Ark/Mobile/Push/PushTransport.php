<?php

namespace App\Ark\Mobile\Push;

/**
 * Transport contract — deliver an ARK-authored packet to a device token.
 *
 * Implementations (Firebase today, others tomorrow) must not interpret
 * shop policy, roles, or routing. ARK decides; transport delivers.
 */
interface PushTransport
{
    public function isAvailable(): bool;

    public function send(string $deviceToken, MobilePushMessage $message): bool;
}
