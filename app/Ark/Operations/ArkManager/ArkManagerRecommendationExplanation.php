<?php

namespace App\Ark\Operations\ArkManager;

final readonly class ArkManagerRecommendationExplanation
{
    public function __construct(
        public string $explanation,
        public string $source,
        public bool $aiEnhanced,
    ) {}
}
