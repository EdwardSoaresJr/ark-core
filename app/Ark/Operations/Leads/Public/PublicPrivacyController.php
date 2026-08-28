<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;

final class PublicPrivacyController
{
    public function __invoke(SeoEngine $seo): View
    {
        return view('public.privacy', [
            ...PublicSurfacePageData::shared(),
            'seo' => $seo->forPrivacyPage()->toArray(),
        ]);
    }
}
