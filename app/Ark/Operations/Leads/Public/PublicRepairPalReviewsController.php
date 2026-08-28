<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;

final class PublicRepairPalReviewsController
{
    public function __invoke(SeoEngine $seo): View
    {
        return view('public.repairpal.reviews', [
            ...PublicSurfacePageData::shared(),
            'seo' => $seo->forRepairPalReviewsPage()->toArray(),
            'repairPalUrl' => PublicSurfaceSettings::current()['trust_signals']['repairpal_url'],
        ]);
    }
}
