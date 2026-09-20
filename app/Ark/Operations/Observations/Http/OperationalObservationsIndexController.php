<?php

namespace App\Ark\Operations\Observations\Http;

use App\Ark\Operations\Observations\OperationalObservationDebugProjection;
use Illuminate\Contracts\View\View;

final class OperationalObservationsIndexController
{
    public function __invoke(OperationalObservationDebugProjection $projection): View
    {
        $result = $projection->resolve();

        return view('operations.observations.index', [
            'rows' => $result['rows'],
            'counts' => $result['counts'],
        ]);
    }
}
