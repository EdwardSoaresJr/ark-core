<?php

namespace App\Ark\Operations\Reports;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportsEndOfDayController
{
    public function __invoke(Request $request): View
    {
        $shopDate = $request->input('date');
        [$from, $to] = OperationalReportDateScope::resolveRange($shopDate, $shopDate);

        return view('operations.reports.end-of-day', [
            'eod' => EndOfDayReportProjection::resolve($from, $to),
            'dateFormAction' => route('operations.reports.end-of-day'),
        ]);
    }
}
