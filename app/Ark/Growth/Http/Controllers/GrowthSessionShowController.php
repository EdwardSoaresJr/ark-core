<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Models\GrowthSession;
use Illuminate\Contracts\View\View;

final class GrowthSessionShowController
{
    public function __invoke(GrowthSession $session): View
    {
        $session->load([
            'touchpoints.content',
            'lastTouch',
            'firstContent',
            'leads',
            'repairOrders',
        ]);

        return view('growth.sessions.show', [
            'session' => $session,
        ]);
    }
}
