<?php

namespace App\Ark\Operations\Intake;

use Illuminate\Http\RedirectResponse;

/**
 * Intake queue index retired — draft/estimate ROs live on the Job Board.
 * Check-in (customer/vehicle) remains at operations.intake.create.
 */
class AdvisorIntakeIndexController
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('operations.index');
    }
}
