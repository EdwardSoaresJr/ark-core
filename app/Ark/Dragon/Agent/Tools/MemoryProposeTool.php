<?php

namespace App\Ark\Dragon\Agent\Tools;

use App\Ark\Dragon\Agent\DragonAgentTool;
use App\Ark\Dragon\Agent\DragonMemoryContext;
use App\Ark\Dragon\Agent\DragonMemoryPrivacy;

final class MemoryProposeTool implements DragonAgentTool
{
    public function name(): string
    {
        return 'memory.propose';
    }

    public function description(): string
    {
        return 'Propose a durable memory. Does not write. ARK stores it only after the coworker confirms. Never use for live RO, hours, assignments, or customer PII.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fact' => ['type' => 'string'],
                'scope_intent' => ['type' => 'string', 'enum' => ['company', 'workstation', 'user']],
                'category' => ['type' => 'string', 'enum' => ['standard', 'preference', 'terminology']],
            ],
            'required' => ['fact', 'scope_intent'],
        ];
    }

    public function invoke(array $arguments): array
    {
        $fact = trim((string) ($arguments['fact'] ?? ''));
        $scope = (string) ($arguments['scope_intent'] ?? 'company');
        $category = (string) ($arguments['category'] ?? 'standard');
        $rejected = DragonMemoryPrivacy::rejectReason($fact);
        if ($rejected !== null) {
            return ['ok' => false, 'persisted' => false, 'error' => $rejected];
        }

        $context = app(DragonMemoryContext::class);
        if ($context->conversation === null) {
            return ['ok' => false, 'persisted' => false, 'error' => 'No conversation to confirm against.'];
        }

        $context->conversation->forceFill([
            'pending_memory' => [
                'fact' => $fact,
                'scope_type' => $scope === 'location' ? 'workstation' : $scope,
                'category' => $category,
            ],
        ])->save();

        return [
            'ok' => true,
            'persisted' => false,
            'needs_confirmation' => true,
            'ask' => $scope === 'user'
                ? 'Should I remember that as your personal preference?'
                : ($scope === 'workstation'
                    ? 'Should I remember that for this station?'
                    : 'Should I remember that as a company-wide shop standard?'),
            '_trace' => ['result_count' => 0, 'scopes' => [$scope]],
        ];
    }
}
