<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;

final class PublicRepairPalCertifiedController
{
    public function __invoke(SeoEngine $seo): View
    {
        return view('public.repairpal.certified', [
            ...PublicSurfacePageData::shared(),
            'seo' => $seo->forRepairPalCertifiedPage()->toArray(),
            'repairPalUrl' => PublicSurfaceSettings::current()['trust_signals']['repairpal_url'],
        ]);
    }
}
