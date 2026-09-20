<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AppointmentConfirmationSmsController
{
    public function __invoke(
        Request $request,
        Appointment $appointment,
        SendAppointmentConfirmationSmsAction $send,
    ): RedirectResponse {
        try {
            $send->execute($appointment, $request->user());
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('operations.appointments.show', $appointment)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('operations.appointments.show', $appointment)
            ->with('status', 'Confirmation text sent.');
    }
}
