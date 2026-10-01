<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Dragon\Agent\DragonMemoryContext;
use App\Ark\Dragon\Agent\RecallDragonMemory;

final class MemoryRecallTool implements DragonAgentTool
{
    public function __construct(private readonly RecallDragonMemory $recall) {}

    public function name(): string
    {
        return 'memory.recall';
    }

    public function description(): string
    {
        return 'Recall ARK-owned durable taught standards and preferences. Use only when a taught shop standard or preference is relevant. Not live ARK records, not ARKademy, not this chat. Live ARK tools beat memory. Location (station) memory applies only to the current station.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'needle' => ['type' => 'string'],
            ],
        ];
    }

    public function invoke(array $arguments): array
    {
        $needle = trim((string) ($arguments['needle'] ?? ''));
        $facts = $this->recall->facts($needle, app(DragonMemoryContext::class));

        return [
            'ok' => true,
            'facts' => $facts,
            '_ark_categories' => ['dragon_memory'],
            '_trace' => [
                'result_count' => count($facts),
                'memory_ids' => array_column($facts, 'id'),
                'keys' => array_column($facts, 'key'),
                'categories' => array_values(array_unique(array_filter(array_column($facts, 'category')))),
                'scopes' => array_values(array_unique(array_column($facts, 'scope'))),
            ],
        ];
    }
}
