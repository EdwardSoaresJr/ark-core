<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\ContentBuilderProjection;
use App\Ark\Growth\Opportunities\OpportunityQueueRepository;
use Illuminate\Contracts\View\View;

final class GrowthOpportunityBuildController
{
    public function __invoke(
        GrowthOpportunity $opportunity,
        ContentBuilderProjection $projection,
        OpportunityQueueRepository $opportunities,
    ): View {
        $opportunity = $opportunities->ensureDraft($opportunity);

        return view('growth.opportunities.build', [
            'builder' => $projection->resolve($opportunity),
        ]);
    }
}
