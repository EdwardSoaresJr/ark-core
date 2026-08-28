<?php

namespace App\Ark\Website\Http\Controllers;

use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Contracts\View\View;

final class WebsiteManageController
{
    public function __invoke(): View
    {
        return view('website.manage', [
            'publicSurfaceSettings' => PublicSurfaceSettings::current(),
            'shop' => ShopSettings::current(),
            'servicePageOptions' => collect(CommonProblemRegistry::localServicePages())
                ->map(fn (array $problem): array => [
                    'slug' => $problem['slug'],
                    'title' => $problem['title'],
                ])
                ->sortBy('title')
                ->values()
                ->all(),
        ]);
    }
}
