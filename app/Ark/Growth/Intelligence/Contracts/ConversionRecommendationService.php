<?php

namespace App\Ark\Growth\Intelligence\Contracts;

interface ConversionRecommendationService
{
    /**
     * @return list<array{page: string, recommendation: string, evidence: array<string, mixed>}>
     */
    public function recommend(): array;
}
