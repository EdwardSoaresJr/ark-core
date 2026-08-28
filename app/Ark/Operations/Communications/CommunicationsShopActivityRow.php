<?php

namespace App\Ark\Operations\Communications;

final readonly class CommunicationsShopActivityRow
{
    public function __construct(
        public int $userId,
        public string $label,
        public string $tone,
    ) {}
}
