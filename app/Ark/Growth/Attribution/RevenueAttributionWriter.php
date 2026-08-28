<?php

namespace App\Ark\Growth\Attribution;

use App\Ark\Growth\Contracts\Operations\RepairOrderClosedPayload;
use App\Ark\Growth\Events\GrowthEventRecorder;
use App\Ark\Growth\Events\GrowthEventType;
use App\Ark\Growth\Models\GrowthAttribution;
use App\Ark\Growth\Models\GrowthContent;

final class RevenueAttributionWriter
{
    public function __construct(
        private readonly GrowthEventRecorder $eventRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(array $attributes): GrowthAttribution
    {
        if (($attributes['repair_order_id'] ?? null) === 0) {
            $attributes['repair_order_id'] = null;
        }

        if (($attributes['repair_order_id'] ?? null) !== null) {
            $existing = GrowthAttribution::query()
                ->where('repair_order_id', $attributes['repair_order_id'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $attribution = GrowthAttribution::query()->create($attributes);

        if ($attribution->growth_content_id !== null) {
            GrowthContent::query()
                ->whereKey($attribution->growth_content_id)
                ->increment('revenue_cents', $attribution->revenue_cents);
        }

        $this->eventRecorder->record(
            type: GrowthEventType::RevenueBooked,
            path: $attribution->landing_page,
            contentId: $attribution->growth_content_id,
            payload: [
                'attribution_id' => $attribution->id,
                'repair_order_id' => $attribution->repair_order_id,
                'revenue_cents' => $attribution->revenue_cents,
            ],
        );

        return $attribution;
    }

    public function fromRepairOrderClosed(RepairOrderClosedPayload $payload): GrowthAttribution
    {
        $contentId = null;
        if ($payload->landingPage !== null) {
            $contentId = GrowthContent::query()
                ->where('path', $payload->landingPage)
                ->value('id');
        }

        return $this->record([
            'growth_session_id' => $payload->growthSessionId,
            'repair_order_id' => $payload->repairOrderId,
            'lead_id' => $payload->leadId,
            'conversation_id' => $payload->conversationId,
            'growth_content_id' => $contentId,
            'source' => $payload->source,
            'campaign' => $payload->campaign,
            'landing_page' => $payload->landingPage,
            'search_query' => $payload->searchQuery,
            'referrer' => $payload->referrer,
            'utm_source' => $payload->attribution['utm_source'] ?? null,
            'utm_medium' => $payload->attribution['utm_medium'] ?? null,
            'utm_campaign' => $payload->attribution['utm_campaign'] ?? null,
            'utm_term' => $payload->attribution['utm_term'] ?? null,
            'utm_content' => $payload->attribution['utm_content'] ?? null,
            'revenue_cents' => $payload->revenueCents,
            'metadata' => $payload->attribution,
        ]);
    }
}
