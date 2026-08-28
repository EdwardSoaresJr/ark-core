<?php

namespace App\Ark\Operations\Communications;

final readonly class CommunicationsShopPendingDeviceRow
{
    public function __construct(
        public int $deviceId,
        public string $name,
        public string $modelLabel,
        public string $macDisplay,
        public string $foundAgoLabel,
    ) {}
}
