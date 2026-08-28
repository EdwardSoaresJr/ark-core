<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Content\ContentBuilderSchema;
use App\Ark\Growth\Events\GrowthOpportunityContentUpdated;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceCriteriaTemplate;
use App\Ark\Growth\Opportunities\OpportunityAcceptanceEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class GrowthOpportunityContentController
{
    public function __invoke(
        Request $request,
        GrowthOpportunity $opportunity,
        OpportunityAcceptanceEvaluator $acceptance,
    ): RedirectResponse {
        if ($request->boolean('toggle_criterion')) {
            return $this->toggleCriterion($request, $opportunity, $acceptance);
        }

        return $this->saveDraft($request, $opportunity, $acceptance);
    }

    private function saveDraft(
        Request $request,
        GrowthOpportunity $opportunity,
        OpportunityAcceptanceEvaluator $acceptance,
    ): RedirectResponse {
        $draft = ContentBuilderSchema::normalize([
            'title' => $request->input('title'),
            'slug' => $request->input('slug'),
            'summary' => $request->input('summary'),
            'symptoms' => $request->input('symptoms'),
            'diagnosis' => $request->input('diagnosis'),
            'common_causes' => $request->input('common_causes'),
            'when_not_to_drive' => $request->input('when_not_to_drive'),
            'faq' => $request->input('faq', []),
            'cta' => $request->input('cta'),
            'related_services' => $request->input('related_services'),
            'related_problems' => $request->input('related_problems'),
            'related_vehicles' => $request->input('related_vehicles'),
        ], $opportunity->title, $opportunity->search_query);

        $opportunity->content_draft = $draft;
        $opportunity->acceptance_criteria = $acceptance->evaluate(
            $opportunity,
            OpportunityAcceptanceCriteriaTemplate::hydrate(
                $opportunity->action_type,
                $opportunity->acceptance_criteria,
            ),
        );

        if (in_array($opportunity->status, [GrowthOpportunityStatus::Discovered, GrowthOpportunityStatus::Accepted], true)) {
            $opportunity->status = GrowthOpportunityStatus::Building;
        }

        $opportunity->save();

        GrowthOpportunityContentUpdated::dispatch($opportunity->fresh());

        return back()->with('status', 'Content checklist saved.');
    }

    private function toggleCriterion(
        Request $request,
        GrowthOpportunity $opportunity,
        OpportunityAcceptanceEvaluator $acceptance,
    ): RedirectResponse {
        $key = (string) $request->input('criterion_key');
        $criteria = OpportunityAcceptanceCriteriaTemplate::hydrate(
            $opportunity->action_type,
            $opportunity->acceptance_criteria,
        );

        $updated = collect($criteria)
            ->map(function (array $item) use ($key, $request): array {
                if ($item['key'] !== $key || $item['kind'] !== 'manual') {
                    return $item;
                }

                return [...$item, 'satisfied' => $request->has('satisfied')];
            })
            ->all();

        $opportunity->acceptance_criteria = $acceptance->evaluate($opportunity, $updated);
        $opportunity->save();

        GrowthOpportunityContentUpdated::dispatch($opportunity->fresh());

        return back()->with('status', 'Acceptance criterion updated.');
    }
}
