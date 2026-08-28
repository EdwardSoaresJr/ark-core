<?php

namespace App\Ark\Website\Projections;

use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;

final class WebsitePublishQueueProjection
{
    /**
     * @return array<string, mixed>|null
     */
    public function resolve(): ?array
    {
        $opportunity = GrowthOpportunity::query()
            ->whereIn('status', [
                GrowthOpportunityStatus::Building,
                GrowthOpportunityStatus::Accepted,
            ])
            ->orderByRaw("CASE status WHEN 'building' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
            ->orderByDesc('priority_score')
            ->first();

        if ($opportunity === null) {
            return null;
        }

        return [
            'id' => $opportunity->id,
            'title' => $opportunity->title,
            'action' => $opportunity->action_type->label(),
            'effort' => $opportunity->effort->label(),
            'status' => $opportunity->status->label(),
            'continue_url' => route('growth.opportunities.build', $opportunity),
            'growth_url' => route('growth.opportunities.index'),
        ];
    }
}
