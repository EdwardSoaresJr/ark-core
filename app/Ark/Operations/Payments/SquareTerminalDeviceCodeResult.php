<?php

namespace App\Ark\Operations\Payments;

final class SquareTerminalDeviceCodeResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $status,
        public readonly ?string $pairBy,
        public readonly ?string $deviceId,
    ) {}

    public function paired(): bool
    {
        return strtoupper($this->status) === 'PAIRED' && filled($this->deviceId);
    }
}
