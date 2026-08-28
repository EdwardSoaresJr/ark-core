<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;

final class PublicTermsController
{
    public function __invoke(SeoEngine $seo): View
    {
        return view('public.terms', [
            ...PublicSurfacePageData::shared(),
            'seo' => $seo->forTermsPage()->toArray(),
        ]);
    }
}
