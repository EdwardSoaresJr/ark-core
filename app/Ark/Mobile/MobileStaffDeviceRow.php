<?php

namespace App\Ark\Mobile;

final class MobileStaffDeviceRow
{
    public function __construct(
        public readonly int $deviceId,
        public readonly string $advisorName,
        public readonly string $deviceName,
        public readonly string $platform,
        public readonly ?string $appVersion,
        public readonly ?string $extension,
        public readonly bool $voiceEnabled,
        public readonly bool $voiceLive,
        public readonly bool $pushTokenRegistered,
        public readonly ?string $lastSeenLabel,
    ) {}
}
