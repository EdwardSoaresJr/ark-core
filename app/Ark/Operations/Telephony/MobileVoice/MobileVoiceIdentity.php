<?php

namespace App\Ark\Operations\Telephony\MobileVoice;

use App\Ark\Mobile\MobileDevice;

final class MobileVoiceIdentity
{
    public static function forDevice(int $userId, int $deviceId): string
    {
        return 'ark-mobile:'.$userId.':'.$deviceId;
    }

    public static function fromDevice(MobileDevice $device): string
    {
        return self::forDevice((int) $device->user_id, (int) $device->id);
    }

    public static function twilioClientAddress(string $identity): string
    {
        return 'client:'.$identity;
    }

    /**
     * @return array{user_id: int, device_id: int}|null
     */
    public static function parse(string $identity): ?array
    {
        if (! preg_match('/^ark-mobile:(\d+):(\d+)$/', trim($identity), $matches)) {
            return null;
        }

        return [
            'user_id' => (int) $matches[1],
            'device_id' => (int) $matches[2],
        ];
    }
}
