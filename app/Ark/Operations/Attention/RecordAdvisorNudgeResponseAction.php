<?php

namespace App\Ark\Operations\Attention;

use App\Models\User;

final class RecordAdvisorNudgeResponseAction
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function execute(
        User $user,
        string $entityKey,
        string $nudgeKey,
        AdvisorNudgeResponseKind $response,
        ?array $metadata = null,
    ): AdvisorNudgeResponse {
        return AdvisorNudgeResponse::query()->create([
            'user_id' => $user->id,
            'entity_key' => $entityKey,
            'nudge_key' => $nudgeKey,
            'response' => $response,
            'metadata' => $metadata,
        ]);
    }
}
