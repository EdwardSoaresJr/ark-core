<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class BreadcrumbSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'BreadcrumbList';
    }

    public function build(array $context): array
    {
        /** @var list<array{name: string, url: string}> $items */
        $items = $context['items'] ?? [];
        $position = 1;
        $listItems = [];

        foreach ($items as $item) {
            $listItems[] = array_filter([
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $item['name'] ?? null,
                'item' => $item['url'] ?? null,
            ]);
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $listItems,
        ];
    }
}
