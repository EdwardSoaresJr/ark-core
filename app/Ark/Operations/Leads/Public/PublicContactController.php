<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;

final class PublicContactController
{
    public function __invoke(SeoEngine $seo): View
    {
        $contact = ContactPageProjection::forDisplay();

        return view('public.contact', [
            ...PublicSurfacePageData::shared(),
            'contact' => $contact,
            'seo' => $seo->forContactPage($contact['faqs'])->toArray(),
        ]);
    }
}
