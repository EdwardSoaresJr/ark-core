<?php

namespace App\Ark\Operations\ShopExcellence;

use App\Ark\Operations\Attention\AdvisorNudgeWeeklyInsightProjection;
use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OwnerBookendController
{
    public function __invoke(
        Request $request,
        OwnerOperationalPulse $pulse,
        AdvisorNudgeWeeklyInsightProjection $nudgeInsight,
    ): View {
        $shopDate = $request->input('date');
        [$from, $to] = OperationalReportDateScope::resolveRange($shopDate, $shopDate);

        return view('operations.owner.bookend', [
            'eod' => EndOfDayReportProjection::resolve($from, $to),
            'priorities' => $pulse->bookendPriorities(),
            'nudgeInsight' => $nudgeInsight->lastSevenDays(),
            'targetReviewStale' => ShopExcellenceTargets::targetReviewStale(),
            'lastTargetReview' => ShopExcellenceTargets::lastTargetReview(),
        ]);
    }
}
