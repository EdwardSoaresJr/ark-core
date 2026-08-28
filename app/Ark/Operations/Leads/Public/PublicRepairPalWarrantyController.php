<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;

final class PublicRepairPalWarrantyController
{
    public function __invoke(SeoEngine $seo): View
    {
        return view('public.repairpal.warranty', [
            ...PublicSurfacePageData::shared(),
            'seo' => $seo->forRepairPalWarrantyPage()->toArray(),
            'repairPalUrl' => PublicSurfaceSettings::current()['trust_signals']['repairpal_url'],
        ]);
    }
}
