<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class AutoRepairSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'AutoRepair';
    }

    public function build(array $context): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'AutoRepair',
            'name' => $context['name'] ?? null,
            'url' => $context['url'] ?? null,
            'description' => $context['description'] ?? null,
            'telephone' => $context['telephone'] ?? null,
            'image' => $context['image'] ?? null,
            'address' => $context['address'] ?? null,
            'sameAs' => $context['sameAs'] ?? null,
        ]);
    }
}
