<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Models\GrowthSession;
use Illuminate\Contracts\View\View;

final class GrowthSessionIndexController
{
    public function __invoke(): View
    {
        $sessions = GrowthSession::query()
            ->withCount(['touchpoints', 'leads', 'repairOrders'])
            ->orderByDesc('started_at')
            ->limit(100)
            ->get();

        return view('growth.sessions.index', [
            'sessions' => $sessions,
        ]);
    }
}
