<?php

namespace App\Ark\Dragon\Agent;

use App\Ark\Dragon\Agent\DragonAgentMemory;
use App\Ark\Dragon\Agent\DragonKnowledgeDocument;
use App\Ark\Dragon\Agent\DragonKnowledgeSource;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

final class ImportArkaiDragonDumpAction
{
    private const DURABLE_MEMORY_TYPES = ['shop_standard', 'fact'];

    /**
     * @return array{sources: int, documents: int, memories: int, superseded: int}
     */
    public function import(string $path): array
    {
        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $sources = 0;
        foreach ($payload['sources'] ?? [] as $source) {
            DragonKnowledgeSource::query()->updateOrCreate(
                ['id' => (string) $source['source_id']],
                [
                    'source_type' => (string) $source['source_type'],
                    'display_name' => (string) $source['display_name'],
                    'authority_class' => (string) ($source['authority_class'] ?? ''),
                    'origin' => (string) ($source['location'] ?? ''),
                ],
            );
            $sources++;
        }

        $documents = 0;
        foreach ($payload['documents'] ?? [] as $document) {
            DragonKnowledgeDocument::query()->updateOrCreate(
                ['id' => (string) $document['document_id']],
                [
                    'source_id' => (string) $document['source_id'],
                    'title' => (string) $document['title'],
                    'source_ref' => (string) ($document['source_ref'] ?? ''),
                    'body' => (string) ($document['normalized_text'] ?? ''),
                    'content_hash' => (string) ($document['content_hash'] ?? ''),
                    'ingested_at' => $document['ingested_at'] ?? now(),
                ],
            );
            $documents++;
        }

        $durable = array_values(array_filter(
            $payload['memories'] ?? [],
            fn (array $row): bool => in_array($row['memory_type'] ?? '', self::DURABLE_MEMORY_TYPES, true),
        ));

        $importedKeys = [];
        $memories = 0;
        foreach ($durable as $memory) {
            $key = $this->memoryKey($memory);
            $importedKeys[] = $key;
            DragonAgentMemory::query()->updateOrCreate(
                ['fact_key' => $key],
                [
                    'fact_value' => trim((string) $memory['content']),
                    'taught_by' => (string) ($memory['source_operator'] ?? 'Edward'),
                    'provenance' => 'arkai-memory:'.(string) $memory['id'],
                    'superseded_at' => null,
                    'scope_type' => 'company',
                    'workstation_id' => null,
                    'user_id' => null,
                    'category' => 'standard',
                    'created_at' => $memory['created_at'] ?? now(),
                ],
            );
            $memories++;
        }

        $superseded = (int) DragonAgentMemory::query()
            ->whereNull('superseded_at')
            ->where('provenance', 'like', 'arkai-memory:%')
            ->when(
                $importedKeys !== [],
                fn ($query) => $query->whereNotIn('fact_key', $importedKeys),
            )
            ->update(['superseded_at' => now()]);

        return compact('sources', 'documents', 'memories', 'superseded');
    }

    /**
     * @param  array<string, mixed>  $memory
     */
    private function memoryKey(array $memory): string
    {
        $id = trim((string) ($memory['id'] ?? ''));
        if ($id === '') {
            throw new InvalidArgumentException('Durable arkai memory is missing an id.');
        }

        return 'arkai:'.$id;
    }
}
