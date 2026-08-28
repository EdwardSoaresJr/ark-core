<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeBoardTechnicianOption
{
    public function __construct(
        public int $id,
        public string $name,
        public string $initials,
    ) {}
}
