<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Runtime\Http\SameOriginUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentStatusController
{
    public function __invoke(
        Request $request,
        Appointment $appointment,
        ApplyAppointmentStatus $applyStatus,
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
        ]);

        $status = AppointmentStatus::from($data['status']);
        $applyStatus->execute($appointment, $status, $request->user());

        return redirect()
            ->to(SameOriginUrl::resolve(
                $request->input('redirect'),
                route('operations.appointments.show', $appointment),
            ))
            ->with('status', 'Appointment marked '.$status->label().'.');
    }
}
