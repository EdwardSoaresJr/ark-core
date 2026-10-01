<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeAttentionDecoration
{
    public function __construct(
        public string $label,
        public string $tone,
    ) {}
}
