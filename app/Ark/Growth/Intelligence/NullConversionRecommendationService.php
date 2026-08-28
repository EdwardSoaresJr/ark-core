<?php

namespace App\Ark\Growth\Intelligence;

use App\Ark\Growth\Intelligence\Contracts\ConversionRecommendationService;

final class NullConversionRecommendationService implements ConversionRecommendationService
{
    public function recommend(): array
    {
        return [];
    }
}
