<?php

namespace App\Ark\Growth\Seo\Schemas;

use App\Ark\Growth\Seo\SchemaBuilder;

final class FaqSchema implements SchemaBuilder
{
    public function type(): string
    {
        return 'FAQPage';
    }

    public function build(array $context): array
    {
        /** @var list<array{question: string, answer: string}> $faqs */
        $faqs = $context['faqs'] ?? [];
        $entities = [];

        foreach ($faqs as $faq) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $faq['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'] ?? '',
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
