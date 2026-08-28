<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Models\GrowthContent;
use Illuminate\View\View;

final class GrowthContentIndexController
{
    public function __invoke(): View
    {
        return view('growth.content.index', [
            'contents' => GrowthContent::query()->orderByDesc('revenue_cents')->paginate(25),
        ]);
    }
}
