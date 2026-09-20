<?php

namespace App\Ark\Operations\Today;

final readonly class AdvisorHomeCockpitHighlight
{
    public function __construct(
        public string $label,
        public string $href,
        public ?string $metaLabel = null,
    ) {}
}
