<?php

namespace App\Ark\Operations\ShopExcellence;

use App\Ark\Operations\Attention\AdvisorNudgeWeeklyInsightProjection;
use App\Ark\Operations\Reports\EndOfDayReportProjection;
use App\Ark\Operations\Reports\OperationalReportDateScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OwnerDayReviewController
{
    public function __invoke(
        Request $request,
        OwnerOperationalPulse $pulse,
        AdvisorNudgeWeeklyInsightProjection $nudgeInsight,
    ): View {
        $shopDate = $request->input('date');
        [$from, $to] = OperationalReportDateScope::resolveRange($shopDate, $shopDate);

        return view('operations.owner.day-review', [
            'eod' => EndOfDayReportProjection::resolve($from, $to),
            'priorities' => $pulse->dayReviewPriorities(),
            'nudgeInsight' => $nudgeInsight->lastSevenDays(),
            'targetReviewStale' => ShopExcellenceTargets::targetReviewStale(),
            'lastTargetReview' => ShopExcellenceTargets::lastTargetReview(),
        ]);
    }
}
