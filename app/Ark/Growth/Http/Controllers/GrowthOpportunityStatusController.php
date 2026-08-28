<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Events\GrowthOpportunityPublished;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceCriteriaTemplate;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class GrowthOpportunityStatusController
{
    public function __invoke(
        Request $request,
        GrowthOpportunity $opportunity,
        OpportunityAcceptanceEvaluator $acceptance,
    ): RedirectResponse {
        $status = GrowthOpportunityStatus::tryFrom((string) $request->input('status'));

        if ($status === null) {
            return back()->withErrors(['status' => 'Invalid status.']);
        }

        if ($status === GrowthOpportunityStatus::Published) {
            $criteria = $acceptance->evaluate(
                $opportunity,
                OpportunityAcceptanceCriteriaTemplate::hydrate(
                    $opportunity->action_type,
                    $opportunity->acceptance_criteria,
                ),
            );

            if (! $acceptance->allRequiredSatisfied($criteria)) {
                return back()->withErrors([
                    'status' => 'Published requires every acceptance criterion. Open the content builder and complete the checklist.',
                ]);
            }
        }

        $previousStatus = $opportunity->status;
        $opportunity->transitionTo($status);

        if ($status === GrowthOpportunityStatus::Published && $previousStatus !== GrowthOpportunityStatus::Published) {
            GrowthOpportunityPublished::dispatch($opportunity->fresh());
        }

        return back()->with('status', 'Opportunity updated.');
    }
}
