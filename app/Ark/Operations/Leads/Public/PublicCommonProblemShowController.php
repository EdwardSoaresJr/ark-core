<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicCommonProblemShowController
{
    public function __construct(
        private readonly CommonProblemLeadForm $leadForm,
        private readonly CommonProblemAuthorityProjection $authority,
    ) {}

    public function __invoke(Request $request, string $slug, SeoEngine $seo): View|RedirectResponse
    {
        $problem = CommonProblemRegistry::find($slug);

        if ($problem === null) {
            $legacyTarget = PublicLegacyRedirect::resolve('common-problems/'.$slug);

            if ($legacyTarget !== null) {
                return redirect()->to($legacyTarget, 301);
            }

            abort(404);
        }

        $shared = PublicSurfacePageData::shared();
        $shopName = $shared['shop']->displayName();

        return view('public.common-problems.show', [
            ...$shared,
            'problem' => $problem,
            'authority' => $this->authority->forProblem($problem),
            'leadForm' => $this->leadForm->forShow($request, $problem, $shopName),
            'seo' => $seo->forCommonProblem($problem)->toArray(),
        ]);
    }
}
