<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Legacy create URL — kept for existing links.
 * Prefer operations.schedule (/app/schedule) for all new CTAs.
 */
class AppointmentCreateController
{
    public function __invoke(
        Request $request,
        ScheduleContextResolver $resolver,
        PresentAppointmentCreateForm $present,
    ): View {
        return $present($request, $resolver->fromRequest($request));
    }
}
