<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentStoreController
{
    use ValidatesAppointments;

    public function __invoke(Request $request): RedirectResponse
    {
        [$data, $warnings] = $this->normalizeAppointmentInput(
            $this->validatedAppointment($request),
            $request,
        );

        $appointment = Appointment::query()->create([
            ...$data,
            'created_by_user_id' => $request->user()->id,
            'status' => $data['status'] ?? AppointmentStatus::Scheduled,
        ]);

        if (! empty($appointment->repair_order_id)
            && $appointment->status->isUpcoming()) {
            $siblings = Appointment::query()
                ->where('repair_order_id', $appointment->repair_order_id)
                ->whereKeyNot($appointment->id)
                ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
                ->get();
            foreach ($siblings as $sibling) {
                app(ApplyAppointmentStatus::class)->execute($sibling, AppointmentStatus::Canceled, $request->user());
            }
        }

        app(RecordAppointmentFact::class)->execute(
            $appointment,
            \App\Ark\Operations\Events\OperationalEventName::AppointmentScheduled,
            $request->user(),
        );

        $redirect = redirect()
            ->route('operations.appointments.show', $appointment)
            ->with('status', 'Appointment scheduled.')
            ->with('appointment_comms_prompt', true);

        if ($warnings !== []) {
            $redirect->with('schedule_warnings', $warnings);
        }

        return $redirect;
    }
}
