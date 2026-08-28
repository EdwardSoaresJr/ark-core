<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentUpdateController
{
    use ValidatesAppointments;

    public function __invoke(
        Request $request,
        Appointment $appointment,
        ApplyAppointmentStatus $applyStatus,
    ): RedirectResponse {
        if ($appointment->status === AppointmentStatus::Canceled) {
            abort(422, 'Canceled appointments cannot be edited.');
        }

        [$data, $warnings] = $this->normalizeAppointmentInput(
            $this->validatedAppointment($request, $appointment),
            $request,
            $appointment,
        );

        $status = $data['status'] ?? $appointment->status;
        unset($data['status'], $data['canceled_at'], $data['arrived_at']);

        $appointment->fill($data)->save();
        $applyStatus->execute($appointment->fresh(), $status instanceof AppointmentStatus
            ? $status
            : AppointmentStatus::from($status));

        $redirect = redirect()
            ->route('operations.appointments.show', $appointment)
            ->with('status', 'Appointment updated.');

        if ($warnings !== []) {
            $redirect->with('schedule_warnings', $warnings);
        }

        return $redirect;
    }
}
