<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;

class PublicFinancingController
{
    public function __invoke(SeoEngine $seo, PublicTrustSignalsProjection $trustSignals): View
    {
        return view('public.financing', [
            ...PublicSurfacePageData::shared(),
            'seo' => $seo->forFinancingPage()->toArray(),
            'trustSignals' => $trustSignals->forDisplay(),
        ]);
    }
}
