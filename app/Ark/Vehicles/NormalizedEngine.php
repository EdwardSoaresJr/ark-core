<?php

namespace App\Ark\Vehicles;

final readonly class NormalizedEngine
{
    public function __construct(
        public ?string $display,
        public ?string $code,
        public ?string $displacementLiters,
    ) {}
}
