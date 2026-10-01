<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeLaborProgress
{
    public function __construct(
        public float $completedHours,
        public float $billedHours,
        public int $percent,
        public string $label,
    ) {}
}
