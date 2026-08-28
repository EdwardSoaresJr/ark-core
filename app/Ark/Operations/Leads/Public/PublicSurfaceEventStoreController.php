<?php

namespace App\Ark\Operations\Leads\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PublicSurfaceEventStoreController
{
    public function __construct(
        private readonly PublicSurfaceEventRecorder $recorder,
    ) {}

    public function __invoke(Request $request): Response
    {
        if (! $this->recorder->enabled()) {
            return response()->noContent();
        }

        $validated = $request->validate([
            'event' => ['required', 'string', Rule::enum(PublicSurfaceEventType::class)],
            'page' => ['nullable', 'string', 'max:128'],
            'variant' => ['nullable', 'string', 'max:32'],
            'placement' => ['nullable', 'string', 'max:32'],
            'source' => ['nullable', 'string', 'max:64'],
            'target' => ['nullable', 'string', 'max:128'],
        ]);

        $type = PublicSurfaceEventType::from($validated['event']);

        if (! $type->clientRecordable()) {
            return response()->noContent(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $request->hasSession()) {
            return response()->noContent();
        }

        $sessionId = (string) $request->session()->getId();
        $attribution = $type === PublicSurfaceEventType::SurfaceViewed
            ? PublicSurfaceAttribution::fromRequest($request)
            : null;
        $context = PublicSurfaceContext::fromInput([
            'page' => $validated['page'] ?? null,
            'variant' => $validated['variant'] ?? null,
            'placement' => $validated['placement'] ?? null,
            'source' => $validated['source'] ?? null,
            'target' => $validated['target'] ?? null,
        ])?->toArray();

        $this->recorder->record($sessionId, $type, attribution: $attribution, context: $context, request: $request);

        return response()->noContent();
    }
}
