<?php

namespace App\Ark\Operations\Communications;

final readonly class CommunicationsShopAttentionRow
{
    public function __construct(
        public string $message,
        public ?int $deviceId = null,
    ) {}
}
