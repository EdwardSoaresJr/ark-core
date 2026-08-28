<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Projections\GrowthIntegrationsProjection;
use Illuminate\Contracts\View\View;

final class GrowthIntegrationsController
{
    public function __invoke(GrowthIntegrationsProjection $projection): View
    {
        return view('growth.integrations.index', [
            'integrations' => $projection->resolve(),
        ]);
    }
}
