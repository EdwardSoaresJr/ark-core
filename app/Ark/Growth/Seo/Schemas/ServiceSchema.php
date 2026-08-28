<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class ServiceSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'Service';
    }

    public function build(array $context): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $context['name'] ?? null,
            'description' => $context['description'] ?? null,
            'provider' => $context['provider'] ?? null,
            'areaServed' => $context['areaServed'] ?? null,
            'url' => $context['url'] ?? null,
        ]);
    }
}
