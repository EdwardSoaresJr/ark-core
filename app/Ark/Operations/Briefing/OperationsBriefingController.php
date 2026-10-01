<?php

namespace App\Ark\Operations\Briefing;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OperationsBriefingController
{
    public function __invoke(Request $request, OperationsBriefingProjection $briefing): View
    {
        return view('operations.briefing', [
            'briefing' => $briefing->forUser(
                $request->user(),
                $request->string('date')->toString() ?: null,
            ),
        ]);
    }
}
