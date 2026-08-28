<?php

namespace App\Ark\Operations\Today\Surface;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class TodayController
{
    public function __invoke(Request $request, TodayProjectionBuilder $today): View
    {
        $projection = $today->forUser($request->user());

        return view('operations.today.index', [
            'today' => $projection,
        ]);
    }
}
