<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class ArticleSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'Article';
    }

    public function build(array $context): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $context['headline'] ?? null,
            'description' => $context['description'] ?? null,
            'author' => $context['author'] ?? null,
            'datePublished' => $context['datePublished'] ?? null,
            'dateModified' => $context['dateModified'] ?? null,
            'image' => $context['image'] ?? null,
            'url' => $context['url'] ?? null,
        ]);
    }
}
