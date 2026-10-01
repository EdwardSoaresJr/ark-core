<?php

namespace App\Ark\Operations\Communications;

final readonly class CommunicationsShopWorkstationRow
{
    public function __construct(
        public int $workstationId,
        public string $name,
        public ?string $rawLocationLabel,
        public string $locationLabel,
        public ?string $currentOperatorLabel,
        public int $deviceCount,
        public ?string $extensionNumber,
        public ?string $extensionDisplayName,
        public ?int $primaryDeviceId,
        public ?string $primaryDeviceName,
        public ?string $primaryDeviceStatusLabel,
        public string $primaryDeviceStatusTone,
        public string $suggestedExtension,
        public string $stationStatusLabel,
        public string $stationStatusTone,
        public bool $isReady,
        public bool $acceptsScheduledWork = false,
    ) {}
}
