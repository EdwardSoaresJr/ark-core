<?php

namespace App\Ark\Operations\Business;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class BusinessCockpitController
{
    public function __invoke(Request $request, BusinessCockpitProjectionBuilder $business): View
    {
        return view('operations.business.index', [
            'business' => $business->forUser($request->user()),
        ]);
    }
}
