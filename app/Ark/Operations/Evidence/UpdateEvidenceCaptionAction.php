<?php

namespace App\Ark\Operations\Evidence;

use Illuminate\Validation\ValidationException;

final class UpdateEvidenceCaptionAction
{
    public function handle(Evidence $evidence, ?string $caption): Evidence
    {
        if (! $evidence->isActive()) {
            throw ValidationException::withMessages([
                'evidence' => 'Retired evidence cannot be edited.',
            ]);
        }

        $caption = $caption !== null ? trim($caption) : null;
        if ($caption === '') {
            $caption = null;
        }

        if ($caption !== null && mb_strlen($caption) > 500) {
            throw ValidationException::withMessages([
                'caption' => 'Caption may be at most 500 characters.',
            ]);
        }

        $evidence->update(['caption' => $caption]);

        return $evidence->fresh() ?? $evidence;
    }
}
