<?php

namespace App\Ark\Growth\Events;

use App\Ark\Growth\Events\GrowthEventType;
use App\Ark\Growth\Models\GrowthEvent;
use Illuminate\Support\Str;

final class GrowthEventRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        GrowthEventType $type,
        ?string $path = null,
        ?int $contentId = null,
        ?string $visitorId = null,
        array $payload = [],
        ?int $growthSessionId = null,
    ): GrowthEvent {
        return GrowthEvent::query()->create([
            'type' => $type,
            'visitor_id' => $visitorId ?? (string) Str::uuid(),
            'growth_session_id' => $growthSessionId,
            'growth_content_id' => $contentId,
            'path' => $path,
            'payload' => $payload,
            'recorded_at' => now(),
        ]);
    }
}
