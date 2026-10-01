<?php

namespace App\Ark\Operations\Communications;

final readonly class CommunicationsShopCoverageRow
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $statusTone,
        public string $summary,
    ) {}
}
