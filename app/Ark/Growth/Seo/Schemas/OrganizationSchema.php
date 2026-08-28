<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class OrganizationSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'Organization';
    }

    public function build(array $context): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $context['name'] ?? null,
            'url' => $context['url'] ?? null,
            'logo' => $context['logo'] ?? null,
            'telephone' => $context['telephone'] ?? null,
            'address' => $context['address'] ?? null,
            'sameAs' => $context['sameAs'] ?? null,
        ]);
    }
}
