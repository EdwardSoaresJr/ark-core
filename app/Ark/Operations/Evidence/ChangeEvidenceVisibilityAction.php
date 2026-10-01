<?php

namespace App\Ark\Operations\Evidence;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeEvidenceVisibilityAction
{
    public function recordInitial(Evidence $evidence, ?User $actor): void
    {
        EvidenceVisibilityHistory::query()->create([
            'evidence_id' => $evidence->id,
            'old_visibility' => null,
            'new_visibility' => EvidenceVisibility::Internal->value,
            'changed_by_user_id' => $actor?->id,
            'changed_at' => now(),
        ]);
    }

    public function handle(Evidence $evidence, EvidenceVisibility $visibility, User $actor): Evidence
    {
        if (! $evidence->isActive()) {
            throw ValidationException::withMessages([
                'evidence' => 'Retired evidence cannot change visibility.',
            ]);
        }

        return DB::transaction(function () use ($evidence, $visibility, $actor): Evidence {
            $evidence = Evidence::query()->lockForUpdate()->findOrFail($evidence->id);
            $old = $evidence->visibility;

            if ($old === $visibility) {
                return $evidence;
            }

            $payload = [
                'visibility' => $visibility,
            ];

            if ($visibility === EvidenceVisibility::Shared && $evidence->shared_at === null) {
                $payload['shared_at'] = now();
            }

            $evidence->update($payload);

            EvidenceVisibilityHistory::query()->create([
                'evidence_id' => $evidence->id,
                'old_visibility' => $old?->value,
                'new_visibility' => $visibility->value,
                'changed_by_user_id' => $actor->id,
                'changed_at' => now(),
            ]);

            return $evidence->fresh() ?? $evidence;
        });
    }
}
