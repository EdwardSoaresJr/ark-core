<?php

namespace App\Ark\Website\Http\Controllers;

use App\Ark\Website\Projections\WebsitePerformanceProjection;
use Illuminate\Contracts\View\View;

final class WebsitePerformanceController
{
    public function __invoke(WebsitePerformanceProjection $projection): View
    {
        return view('website.performance', [
            'performance' => $projection->resolve(),
        ]);
    }
}
