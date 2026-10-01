<?php

namespace App\Ark\Operations\Scoreboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShopOperatingScoreboardController
{
    public function __invoke(Request $request, ShopOperatingScoreboard $scoreboard): View
    {
        abort_unless(ShopOperatingScoreboardAccess::allows($request->user()), 403);

        $access = ShopOperatingScoreboardAccess::for($request->user());
        $period = ShopOperatingScoreboardPeriod::resolve($request->query('period'));
        $wall = $request->query('display') === 'wall';
        $snapshot = $scoreboard->snapshot($period);
        $focus = $wall ? null : $request->query('focus');
        $agingDays = (int) $request->query('days', 10);
        $drilldown = null;

        if (is_string($focus) && $focus !== '' && ShopOperatingScoreboardAccess::canOpenFocus($request->user(), $focus)) {
            $drilldown = $scoreboard->drilldown(
                $period,
                $focus,
                $agingDays,
                $access['repair_orders'],
            );
        }

        $viewData = [
            'access' => $access,
            'snapshot' => $snapshot,
            'periodKey' => $period['key'],
            'wall' => $wall,
            'drilldown' => $drilldown,
            'focus' => is_string($focus) ? $focus : null,
            'agingDays' => $agingDays,
        ];

        if ($request->query('fragment') === '1') {
            return view('operations.scoreboard.partials.board', $viewData);
        }

        if ($wall) {
            return view('operations.scoreboard.wall', $viewData);
        }

        return view('operations.scoreboard.show', $viewData);
    }
}
