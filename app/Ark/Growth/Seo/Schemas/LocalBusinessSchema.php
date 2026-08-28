<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class LocalBusinessSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'LocalBusiness';
    }

    public function build(array $context): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $context['name'] ?? null,
            'url' => $context['url'] ?? null,
            'telephone' => $context['telephone'] ?? null,
            'address' => $context['address'] ?? null,
            'geo' => $context['geo'] ?? null,
            'openingHoursSpecification' => $context['openingHoursSpecification'] ?? null,
        ]);
    }
}
