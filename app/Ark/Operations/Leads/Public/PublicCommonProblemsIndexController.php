<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PublicCommonProblemsIndexController
{
    public function __construct(
        private readonly CommonProblemLeadForm $leadForm,
        private readonly CommonProblemPopularityProjection $popularity,
    ) {}

    public function __invoke(Request $request, SeoEngine $seo): View
    {
        $shared = PublicSurfacePageData::shared();
        $shopName = $shared['shop']->displayName();

        return view('public.common-problems.index', [
            ...$shared,
            'problems' => collect(CommonProblemRegistry::all())
                ->sortBy([
                    ['tier', 'asc'],
                    ['title', 'asc'],
                ])
                ->values()
                ->all(),
            'symptomProblems' => $this->popularity->sortByPopularity(
                CommonProblemRegistry::featuredForIndexSymptoms(),
            ),
            'localServiceProblems' => $this->popularity->sortByPopularity(
                PublicSurfaceSettings::shopServicesForDisplay(),
            ),
            'searchCatalog' => CommonProblemRegistry::searchCatalog(),
            'localServicesSearchCatalog' => PublicSurfaceSettings::shopServicesSearchCatalog(),
            'leadForm' => $this->leadForm->forIndex($request, $shopName),
            'seo' => $seo->forCommonProblemsIndex()->toArray(),
        ]);
    }
}
