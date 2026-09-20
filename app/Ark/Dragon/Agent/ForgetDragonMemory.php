<?php

namespace App\Ark\Dragon\Agent;

final class ForgetDragonMemory
{
    public function forget(DragonAgentMemory $memory): void
    {
        if ($memory->superseded_at !== null) {
            return;
        }

        $memory->forceFill([
            'superseded_at' => now(),
            'provenance' => trim((string) $memory->provenance.'|forget'),
        ])->save();
    }
}
