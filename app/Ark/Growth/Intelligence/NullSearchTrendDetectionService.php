<?php

namespace App\Ark\Growth\Intelligence;

use App\Ark\Growth\Intelligence\Contracts\SearchTrendDetectionService;

final class NullSearchTrendDetectionService implements SearchTrendDetectionService
{
    public function detect(): array
    {
        return [];
    }
}
