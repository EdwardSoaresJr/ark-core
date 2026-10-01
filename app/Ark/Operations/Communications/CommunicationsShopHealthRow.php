<?php

namespace App\Ark\Operations\Communications;

final readonly class CommunicationsShopHealthRow
{
    public function __construct(
        public string $label,
        public bool $passed,
        public string $detail,
    ) {}
}
