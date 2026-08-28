<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class VehicleSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'Vehicle';
    }

    public function build(array $context): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Vehicle',
            'name' => $context['name'] ?? null,
            'brand' => $context['brand'] ?? null,
            'model' => $context['model'] ?? null,
            'vehicleModelDate' => $context['vehicleModelDate'] ?? null,
            'description' => $context['description'] ?? null,
            'url' => $context['url'] ?? null,
        ]);
    }
}
