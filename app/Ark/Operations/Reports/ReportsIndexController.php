<?php

namespace App\Ark\Operations\Reports;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportsIndexController
{
    public function __invoke(Request $request): View
    {
        return view('operations.reports.index', [
            'sections' => OperationalReportsCatalog::forUser($request->user())->sections(),
        ]);
    }
}
