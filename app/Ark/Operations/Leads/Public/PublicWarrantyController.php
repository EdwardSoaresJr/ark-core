<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;

final class PublicWarrantyController
{
    public function __invoke(SeoEngine $seo): View
    {
        return view('public.warranty', [
            ...PublicSurfacePageData::shared(),
            'seo' => $seo->forWarrantyPage()->toArray(),
        ]);
    }
}
