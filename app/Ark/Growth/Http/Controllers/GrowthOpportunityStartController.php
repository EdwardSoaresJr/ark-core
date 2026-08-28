<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceCriteriaTemplate;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceEvaluator;
use App\Ark\Growth\Opportunities\OpportunityQueueRepository;
use Illuminate\Http\RedirectResponse;

final class GrowthOpportunityStartController
{
    public function __invoke(
        GrowthOpportunity $opportunity,
        OpportunityQueueRepository $opportunities,
        OpportunityAcceptanceEvaluator $acceptance,
    ): RedirectResponse {
        $opportunity = $opportunities->ensureDraft($opportunity);

        if ($opportunity->status === GrowthOpportunityStatus::Discovered) {
            $opportunity->transitionTo(GrowthOpportunityStatus::Accepted);
        }

        if ($opportunity->status === GrowthOpportunityStatus::Accepted) {
            $opportunity->transitionTo(GrowthOpportunityStatus::Building);
        }

        $opportunity->acceptance_criteria = $acceptance->evaluate(
            $opportunity,
            OpportunityAcceptanceCriteriaTemplate::hydrate(
                $opportunity->action_type,
                $opportunity->acceptance_criteria,
            ),
        );
        $opportunity->save();

        return redirect()
            ->route('growth.opportunities.build', $opportunity)
            ->with('status', 'Draft ready — review and publish when it looks right.');
    }
}
