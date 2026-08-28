<?php

namespace App\Ark\Growth\Listeners;

use App\Ark\Growth\Contracts\Operations\PublicSurfaceActivityRecorded;
use App\Ark\Growth\Events\GrowthEventRecorder;
use App\Ark\Growth\Events\GrowthEventType;
use App\Ark\Growth\Sessions\GrowthSessionResolver;
use App\Ark\Operations\Leads\Public\PublicSurfaceEventType;

final class RecordPublicSurfaceGrowthActivity
{
    public function __construct(
        private readonly GrowthSessionResolver $sessions,
        private readonly GrowthEventRecorder $events,
    ) {}

    public function handle(PublicSurfaceActivityRecorded $event): void
    {
        try {
            $this->record($event);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function record(PublicSurfaceActivityRecorded $event): void
    {
        if (! config('growth.enabled', true)) {
            return;
        }

        $payload = $event->payload;

        if ($payload->laravelSessionId === '') {
            return;
        }

        $session = $this->sessions->resolveFromActivity($payload);

        if ($session === null) {
            return;
        }

        $touchpointType = $this->sessions->mapSurfaceEventType($payload->surfaceEventType);

        if ($touchpointType === null) {
            return;
        }

        $touch = $this->sessions->touchFromPayload($payload);
        $path = $touch['landing_page'];
        $touchPayload = array_filter([
            'surface_event' => $payload->surfaceEventType,
            'attribution_channel' => $payload->attributionChannel,
            'context' => $payload->context,
            'lead_id' => $payload->leadId,
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        $this->sessions->recordTouchpoint($session, $touchpointType, $path, $touchPayload);
        $this->sessions->applyLastTouch($session, $touch);

        $contentId = $path !== null ? app(\App\Ark\Growth\Content\ContentRegistry::class)->findByPath($path)?->id : null;

        $growthEventType = $this->mapGrowthEventType($payload->surfaceEventType);

        if ($growthEventType !== null) {
            $this->events->record(
                type: $growthEventType,
                path: $path,
                contentId: $contentId,
                visitorId: $session->visitor_id,
                payload: $touchPayload,
                growthSessionId: $session->id,
            );
        }

        if ($payload->leadId !== null) {
            $this->sessions->linkLead($session, $payload->leadId);

            if ($payload->surfaceEventType === PublicSurfaceEventType::LeadCreated->value) {
                $lead = \App\Ark\Operations\Leads\Lead::query()->find($payload->leadId);
                if ($lead?->conversation_id !== null) {
                    $this->sessions->linkConversation($session, (int) $lead->conversation_id);
                }
            }
        }
    }

    private function mapGrowthEventType(string $surfaceType): ?GrowthEventType
    {
        $enum = PublicSurfaceEventType::tryFrom($surfaceType);

        if ($enum === null) {
            return null;
        }

        return match ($enum) {
            PublicSurfaceEventType::SurfaceViewed => GrowthEventType::PageViewed,
            PublicSurfaceEventType::FormExposed => GrowthEventType::CtaClicked,
            PublicSurfaceEventType::LeadStarted => GrowthEventType::EstimateStarted,
            PublicSurfaceEventType::LeadSubmitted => GrowthEventType::LeadSubmitted,
            PublicSurfaceEventType::LeadCreated => GrowthEventType::LeadCreated,
            PublicSurfaceEventType::CallClicked => GrowthEventType::CallClicked,
            PublicSurfaceEventType::TextClicked => GrowthEventType::CtaClicked,
            PublicSurfaceEventType::CommonProblemLinkClicked => GrowthEventType::CtaClicked,
        };
    }
}
