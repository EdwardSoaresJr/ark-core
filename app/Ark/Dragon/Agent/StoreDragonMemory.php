<?php

namespace App\Ark\Dragon\Agent;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class StoreDragonMemory
{
    public function store(
        DragonMemoryContext $context,
        string $fact,
        string $scopeType,
        string $category = 'standard',
        ?int $supersedesId = null,
    ): DragonAgentMemory {
        $fact = trim($fact);
        $rejected = DragonMemoryPrivacy::rejectReason($fact);
        if ($rejected !== null) {
            throw new InvalidArgumentException($rejected);
        }

        $scopeType = match ($scopeType) {
            'company', 'workstation', 'user' => $scopeType,
            'location' => 'workstation',
            default => throw new InvalidArgumentException('Invalid memory scope.'),
        };

        $workstationId = null;
        $userId = null;

        if ($scopeType === 'company') {
            if (! $context->canWriteCompany()) {
                throw new InvalidArgumentException('Company-wide memory requires admin or settings authority.');
            }
        } elseif ($scopeType === 'workstation') {
            if ($context->workstation === null) {
                throw new InvalidArgumentException('This station is not known, so I cannot store location memory.');
            }
            if (! $context->canWriteWorkstation()) {
                throw new InvalidArgumentException('You are not authorized to teach this station.');
            }
            $workstationId = $context->workstation->id;
        } else {
            if (! $context->canWriteUser()) {
                throw new InvalidArgumentException('Personal memory can only be stored for yourself.');
            }
            $userId = $context->user?->id;
            $category = 'preference';
        }

        if ($supersedesId !== null) {
            DragonAgentMemory::query()
                ->where('id', $supersedesId)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);
        }

        return DragonAgentMemory::query()->create([
            'fact_key' => 'taught:'.(string) Str::uuid(),
            'fact_value' => $fact,
            'taught_by' => $context->taughtBy,
            'provenance' => $supersedesId ? 'taught:correct' : 'taught:explicit',
            'scope_type' => $scopeType,
            'workstation_id' => $workstationId,
            'user_id' => $userId,
            'category' => $category,
            'supersedes_id' => $supersedesId,
            'superseded_at' => null,
        ]);
    }
}
