<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Canonical schedule entry — /app/schedule.
 * Product language: schedule this customer. Implementation stays Appointment create.
 */
class ScheduleEntryController
{
    public function __invoke(
        Request $request,
        ScheduleContextResolver $resolver,
        PresentAppointmentCreateForm $present,
    ): View {
        return $present($request, $resolver->fromRequest($request));
    }
}
