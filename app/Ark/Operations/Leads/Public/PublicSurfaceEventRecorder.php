<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Contracts\Operations\PublicSurfaceActivityPayload;
use App\Ark\Growth\Contracts\Operations\PublicSurfaceActivityRecorded;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PublicSurfaceEventRecorder
{
    public function enabled(): bool
    {
        return PublicSurfaceSettings::instrumentationEnabled();
    }

    public function record(
        string $sessionId,
        PublicSurfaceEventType $type,
        ?int $leadId = null,
        ?string $attribution = null,
        ?array $context = null,
        ?Request $request = null,
    ): ?PublicSurfaceEvent {
        if (! $this->enabled() || $sessionId === '') {
            return null;
        }

        if ($type->dedupePerSession() && $this->sessionHasEvent($sessionId, $type, $context)) {
            return null;
        }

        $now = Carbon::now();

        $event = PublicSurfaceEvent::query()->create([
            'event' => $type->value,
            'session_id' => $sessionId,
            'lead_id' => $leadId,
            'attribution' => $attribution,
            'context' => $context,
            'occurred_at' => $now,
            'created_at' => $now,
        ]);

        if (config('growth.enabled', true) && $request !== null) {
            try {
                PublicSurfaceActivityRecorded::dispatch(new PublicSurfaceActivityPayload(
                    laravelSessionId: $sessionId,
                    surfaceEventType: $type->value,
                    leadId: $leadId,
                    attributionChannel: $attribution,
                    context: $context,
                    requestMeta: PublicSurfaceRequestSnapshot::capture($request, $context),
                    visitorId: PublicSurfaceRequestSnapshot::visitorId($request),
                ));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $event;
    }

    public function recordFormExposure(Request $request, PublicSurfaceContext $context): ?PublicSurfaceEvent
    {
        if (! $request->hasSession()) {
            return null;
        }

        return $this->record(
            (string) $request->session()->getId(),
            PublicSurfaceEventType::FormExposed,
            context: $context->toArray(),
            request: $request,
        );
    }

    private function sessionHasEvent(string $sessionId, PublicSurfaceEventType $type, ?array $context = null): bool
    {
        $query = PublicSurfaceEvent::query()
            ->where('session_id', $sessionId)
            ->where('event', $type->value);

        if ($context !== null && filled($context['page'] ?? null)) {
            $query->where('context->page', $context['page']);
        }

        return $query->exists();
    }
}
