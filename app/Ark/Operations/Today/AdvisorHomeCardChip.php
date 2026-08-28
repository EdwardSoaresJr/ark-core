<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeCardChip
{
    public function __construct(
        public string $label,
        public string $tone,
    ) {}
}
