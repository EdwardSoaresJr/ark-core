<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Content\ContentBuilderSchema;
use App\Ark\Growth\Content\ContentDraftProblemProjection;
use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Seo\SeoEngine;
use App\Ark\Operations\Leads\Public\CommonProblemAuthorityProjection;
use App\Ark\Operations\Leads\Public\CommonProblemLeadForm;
use App\Ark\Operations\Leads\Public\PublicSurfacePageData;
use Illuminate\Contracts\View\View;

final class GrowthOpportunityPreviewController
{
    public function __invoke(
        GrowthOpportunity $opportunity,
        SeoEngine $seo,
        CommonProblemLeadForm $leadForm,
        CommonProblemAuthorityProjection $authority,
    ): View {
        $draft = ContentBuilderSchema::normalize(
            $opportunity->content_draft,
            $opportunity->title,
            $opportunity->search_query,
        );

        $problem = ContentDraftProblemProjection::fromDraft($draft);

        abort_if($problem['slug'] === '', 404);

        $shared = PublicSurfacePageData::shared();
        $shopName = $shared['shop']->displayName();

        $seoMeta = $seo->forCommonProblem($problem)->toArray();
        $seoMeta['title'] .= ' (Staff preview)';
        $seoMeta['robots'] = 'noindex, nofollow';
        $seoMeta['indexable'] = false;

        return view('public.common-problems.show', [
            ...$shared,
            'problem' => $problem,
            'authority' => $authority->forProblem($problem),
            'leadForm' => $leadForm->forShow(request(), $problem, $shopName),
            'seo' => $seoMeta,
            'staffPreview' => true,
            'previewPublicPath' => $problem['path'],
        ]);
    }
}
