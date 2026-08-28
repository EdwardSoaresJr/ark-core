<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Models\GrowthRedirect;
use Illuminate\View\View;

final class GrowthRedirectIndexController
{
    public function __invoke(): View
    {
        return view('growth.redirects.index', [
            'redirects' => GrowthRedirect::query()->orderByDesc('hit_count')->paginate(25),
        ]);
    }
}
