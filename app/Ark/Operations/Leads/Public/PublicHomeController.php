<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PublicHomeController
{
    public function __construct(
        private readonly PublicHomePageData $homePageData,
    ) {}

    public function __invoke(SeoEngine $seo): View|RedirectResponse
    {
        if (filled(request('concern'))) {
            return redirect()->route('public.book', request()->only('concern'));
        }

        return view('public.home', $this->homePageData->forView($seo));
    }
}
