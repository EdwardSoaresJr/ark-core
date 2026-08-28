<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;

final class PublicRepairPalHubController
{
    public function __invoke(SeoEngine $seo): View
    {
        return view('public.repairpal.index', [
            ...PublicSurfacePageData::shared(),
            'seo' => $seo->forRepairPalHubPage()->toArray(),
            'repairPalUrl' => PublicSurfaceSettings::current()['trust_signals']['repairpal_url'],
        ]);
    }
}
