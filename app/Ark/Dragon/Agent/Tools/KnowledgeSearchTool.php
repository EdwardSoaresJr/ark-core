<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Dragon\Agent\DragonKnowledgeDocument;
use App\Ark\Dragon\Agent\DragonKnowledgeSource;

final class KnowledgeSearchTool implements DragonAgentTool
{
    public function name(): string
    {
        return 'knowledge.search';
    }

    public function description(): string
    {
        return 'Search ARK-hosted Dragon Knowledge. Sources stay separate: website, arkademy, sop, excellence. Use for website copy, ARKademy lessons, or published shop pages. Empty hits mean say you do not have that document — never invent pages.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
                'source' => [
                    'type' => 'string',
                    'enum' => ['website', 'arkademy', 'sop', 'excellence', 'any'],
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function invoke(array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $source = (string) ($arguments['source'] ?? 'any');

        $documents = DragonKnowledgeDocument::query()
            ->with('source')
            ->when($source !== 'any', function ($builder) use ($source): void {
                $builder->whereHas('source', fn ($inner) => $inner->where('source_type', $source));
            })
            ->where(function ($builder) use ($query): void {
                $builder->where('title', 'like', '%'.$query.'%')
                    ->orWhere('body', 'like', '%'.$query.'%');
            })
            ->orderBy('title')
            ->limit(8)
            ->get();

        $hits = $documents->map(function (DragonKnowledgeDocument $document): array {
            $source = $document->source;
            $body = trim($document->body);

            return [
                'source' => $source instanceof DragonKnowledgeSource ? $source->source_type : $document->source_id,
                'authority' => $source instanceof DragonKnowledgeSource ? $source->authority_class : null,
                'title' => $document->title,
                'url' => $document->source_ref,
                'snippet' => mb_substr($body, 0, 500),
            ];
        })->all();

        return [
            'ok' => true,
            '_ark_categories' => ['knowledge'],
            'query' => $query,
            'source_filter' => $source,
            'hits' => $hits,
            'hosted_in_ark' => true,
            'empty' => $hits === []
                ? 'No matching ARK-hosted documents. Do not invent website or ARKademy copy.'
                : null,
        ];
    }
}
