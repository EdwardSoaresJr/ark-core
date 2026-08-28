<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class WebsiteSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'WebSite';
    }

    public function build(array $context): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $context['name'] ?? null,
            'url' => $context['url'] ?? null,
            'potentialAction' => $context['potentialAction'] ?? null,
        ]);
    }
}
