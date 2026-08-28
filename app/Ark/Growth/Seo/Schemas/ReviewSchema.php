<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class ReviewSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'Review';
    }

    public function build(array $context): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Review',
            'author' => $context['author'] ?? null,
            'reviewRating' => $context['reviewRating'] ?? null,
            'reviewBody' => $context['reviewBody'] ?? null,
            'itemReviewed' => $context['itemReviewed'] ?? null,
        ]);
    }
}
