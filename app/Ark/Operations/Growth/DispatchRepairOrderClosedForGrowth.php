<?php

namespace App\Ark\Operations\Growth;

use App\Ark\Growth\Contracts\Operations\RepairOrderClosedForGrowth;
use App\Ark\Growth\Contracts\Operations\RepairOrderClosedPayload;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\RepairOrderCollectionDisposition;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Operations-side bridge: paid post closes the revenue loop for Growth attribution.
 */
final class DispatchRepairOrderClosedForGrowth
{
    public function __construct(
        private readonly BalanceDueCalculator $balanceDue,
    ) {}

    public function dispatch(RepairOrder $repairOrder): void
    {
        if (! config('growth.enabled', true)) {
            return;
        }

        $disposition = RepairOrderCollectionDisposition::tryFromMixed($repairOrder->collection_disposition);

        if ($disposition->excludesFromPostedSales()) {
            return;
        }

        $projection = $this->balanceDue->projectForRepairOrder($repairOrder);
        $revenueCents = (int) data_get($projection->invoice?->snapshot_json, 'totals.total_cents', 0);

        if ($revenueCents <= 0) {
            return;
        }

        $lead = Lead::query()->where('repair_order_id', $repairOrder->id)->first();
        $growthSessionId = $repairOrder->growth_session_id ?? $lead?->growth_session_id;
        $attribution = $this->extractAttribution($lead, $growthSessionId);

        RepairOrderClosedForGrowth::dispatch(new RepairOrderClosedPayload(
            repairOrderId: $repairOrder->id,
            revenueCents: $revenueCents,
            leadId: $lead?->id,
            conversationId: $lead?->conversation_id,
            growthSessionId: $growthSessionId,
            landingPage: $attribution['landing_page'] ?? null,
            searchQuery: $attribution['search_query'] ?? null,
            source: $attribution['source'] ?? $lead?->source?->value,
            campaign: $attribution['campaign'] ?? null,
            referrer: $lead?->ingress_referrer,
            attribution: $attribution,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function extractAttribution(?Lead $lead, ?int $growthSessionId = null): array
    {
        if ($growthSessionId !== null) {
            $session = \App\Ark\Growth\Models\GrowthSession::query()->find($growthSessionId);

            if ($session !== null) {
                return array_filter([
                    'growth_session_id' => $session->id,
                    'landing_page' => $session->first_landing_page,
                    'search_query' => $session->first_search_query,
                    'source' => $session->utm_source,
                    'campaign' => $session->first_campaign ?? $session->utm_campaign,
                    'referrer' => $session->first_referrer,
                    'utm_source' => $session->utm_source,
                    'utm_medium' => $session->utm_medium,
                    'utm_campaign' => $session->utm_campaign,
                    'utm_term' => $session->utm_term,
                    'utm_content' => $session->utm_content,
                    'first_touch' => [
                        'landing_page' => $session->first_landing_page,
                        'referrer' => $session->first_referrer,
                        'search_query' => $session->first_search_query,
                        'campaign' => $session->first_campaign,
                    ],
                ], fn (mixed $value): bool => $value !== null && $value !== '');
            }
        }

        if ($lead === null) {
            return [];
        }

        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $growth = is_array($metadata['growth'] ?? null) ? $metadata['growth'] : [];
        $utm = is_array($metadata['utm'] ?? null) ? $metadata['utm'] : [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            if (! isset($metadata[$key])) {
                continue;
            }

            $utm[str_replace('utm_', '', $key)] = $metadata[$key];
        }

        $landingPage = $growth['landing_page']
            ?? $metadata['landing_page']
            ?? $this->landingPageFromPublicSurface($metadata);

        $searchQuery = $growth['search_query']
            ?? $utm['term']
            ?? $this->searchQueryFromReferrer($lead->ingress_referrer);

        return array_filter([
            'landing_page' => is_string($landingPage) ? $landingPage : null,
            'search_query' => is_string($searchQuery) ? $searchQuery : null,
            'source' => $growth['source'] ?? $utm['source'] ?? $lead->source?->value,
            'campaign' => $growth['campaign'] ?? $utm['campaign'] ?? null,
            'utm_source' => $utm['source'] ?? null,
            'utm_medium' => $utm['medium'] ?? null,
            'utm_campaign' => $utm['campaign'] ?? null,
            'utm_term' => $utm['term'] ?? null,
            'utm_content' => $utm['content'] ?? null,
            'public_surface' => $metadata['public_surface'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function landingPageFromPublicSurface(array $metadata): ?string
    {
        $surface = $metadata['public_surface'] ?? null;

        if (! is_array($surface)) {
            return null;
        }

        $path = trim((string) ($surface['path'] ?? ''));

        return $path !== '' ? '/'.trim($path, '/') : null;
    }

    private function searchQueryFromReferrer(?string $referrer): ?string
    {
        if ($referrer === null || $referrer === '') {
            return null;
        }

        $parts = parse_url($referrer);

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! str_contains($host, 'google.') && ! str_contains($host, 'bing.')) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        $term = trim((string) ($query['q'] ?? $query['p'] ?? ''));

        return $term !== '' ? $term : null;
    }
}
