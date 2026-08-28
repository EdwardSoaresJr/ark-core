<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Events\GrowthEventRecorder;
use App\Ark\Growth\Events\GrowthEventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GrowthEventStoreController
{
    public function __invoke(Request $request, GrowthEventRecorder $recorder): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'path' => ['nullable', 'string', 'max:255'],
            'visitor_id' => ['nullable', 'uuid'],
            'payload' => ['nullable', 'array'],
        ]);

        $type = GrowthEventType::tryFrom($validated['type']);
        if ($type === null) {
            return response()->json(['message' => 'Unknown event type.'], 422);
        }

        $event = $recorder->record(
            type: $type,
            path: $validated['path'] ?? null,
            visitorId: $validated['visitor_id'] ?? null,
            payload: $validated['payload'] ?? [],
        );

        return response()->json(['id' => $event->id], 201);
    }
}
