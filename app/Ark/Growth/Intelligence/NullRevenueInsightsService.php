<?php

namespace App\Ark\Growth\Intelligence;

use App\Ark\Growth\Intelligence\Contracts\RevenueInsightsService;

final class NullRevenueInsightsService implements RevenueInsightsService
{
    public function insights(): array
    {
        return [];
    }
}
