<?php

namespace App\Ark\Operations\Briefing\Rules;

use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Growth\Models\GrowthTouchpoint;
use App\Ark\Growth\Sessions\GrowthTouchpointType;
use App\Ark\Operations\Briefing\BriefingConfidence;
use App\Ark\Operations\Briefing\BriefingContext;
use App\Ark\Operations\Briefing\BriefingEvidenceItem;
use App\Ark\Operations\Briefing\BriefingItem;
use App\Ark\Operations\Briefing\BriefingPriority;
use App\Ark\Operations\Leads\Lead;

final class HighPerformingLandingPageBriefingRule implements BriefingRule
{
    public function key(): string
    {
        return 'high_performing_landing_page';
    }

    public function items(BriefingContext $context): array
    {
        $leadIds = Lead::query()
            ->whereBetween('created_at', [$context->yesterdayFrom, $context->yesterdayTo])
            ->whereNotNull('growth_session_id')
            ->pluck('growth_session_id');

        if ($leadIds->isEmpty()) {
            return [];
        }

        $topPage = GrowthSession::query()
            ->whereIn('id', $leadIds)
            ->whereNotNull('first_landing_page')
            ->selectRaw('first_landing_page, COUNT(*) as lead_count')
            ->groupBy('first_landing_page')
            ->orderByDesc('lead_count')
            ->first();

        if ($topPage === null || (int) $topPage->lead_count < 2) {
            return [];
        }

        $page = (string) $topPage->first_landing_page;
        $count = (int) $topPage->lead_count;

        $touchpoints = GrowthTouchpoint::query()
            ->whereHas('session', fn ($q) => $q->where('first_landing_page', $page))
            ->whereBetween('recorded_at', [$context->yesterdayFrom, $context->yesterdayTo])
            ->orderByDesc('recorded_at')
            ->limit(3)
            ->get();

        return [
            new BriefingItem(
                key: $this->key(),
                headline: basename($page).' produced '.$count.' leads yesterday',
                summary: 'Highest-performing landing page · '.$page,
                priority: BriefingPriority::Low,
                confidence: new BriefingConfidence(
                    score: 80,
                    reason: 'Lead count grouped by immutable Growth session first landing page.',
                    signals: [
                        ['label' => 'Leads recorded yesterday', 'satisfied' => true],
                        ['label' => 'Growth session attribution', 'satisfied' => true],
                    ],
                    facts: [
                        ['label' => 'Landing page', 'value' => $page],
                        ['label' => 'Leads', 'value' => (string) $count],
                    ],
                ),
                evidenceItems: $touchpoints->map(
                    fn (GrowthTouchpoint $tp): BriefingEvidenceItem => new BriefingEvidenceItem(
                        sourceType: 'growth_touchpoint',
                        summary: $tp->type->label(),
                        occurredAt: $tp->recorded_at ?? now(),
                        detail: $tp->path,
                        sourceId: $tp->id,
                        sourceLabel: 'Growth touchpoint',
                    ),
                )->all(),
                actionUrl: route('growth.journey-explorer', ['q' => 'common_path_by_landing', 'keyword' => basename($page)]),
                actionLabel: 'Explore journeys',
            ),
        ];
    }
}
