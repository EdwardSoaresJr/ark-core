<?php

namespace App\Ark\Operations\Communications;

final readonly class CommunicationsShopDeviceRow
{
    public function __construct(
        public int $deviceId,
        public string $name,
        public string $statusTone,
        public string $statusLabel,
        public ?string $currentOperatorLabel,
        public ?string $workstationName = null,
    ) {}
}
