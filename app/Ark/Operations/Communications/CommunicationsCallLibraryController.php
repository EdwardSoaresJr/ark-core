<?php

namespace App\Ark\Operations\Communications;

use Illuminate\Http\Request;
use Illuminate\View\View;

final class CommunicationsCallLibraryController
{
    public function __invoke(Request $request, CallLibraryProjection $projection): View
    {
        return view('operations.communications.calls.index', [
            'library' => $projection->build($request),
        ]);
    }
}
