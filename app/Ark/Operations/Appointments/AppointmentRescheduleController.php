<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentRescheduleController
{
    public function __invoke(Request $request, Appointment $appointment): JsonResponse|RedirectResponse
    {
        if ($appointment->status === AppointmentStatus::Canceled) {
            abort(422, 'Canceled appointments cannot be moved.');
        }

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'technician_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
        ]);

        $data = [
            'customer_id' => $appointment->customer_id,
            'vehicle_id' => $appointment->vehicle_id,
            'advisor_user_id' => $appointment->advisor_user_id,
            'technician_user_id' => array_key_exists('technician_user_id', $validated)
                ? $validated['technician_user_id']
                : $appointment->technician_user_id,
            'workstation_id' => array_key_exists('workstation_id', $validated)
                ? $validated['workstation_id']
                : $appointment->workstation_id,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'estimated_labor_hours' => $appointment->estimated_labor_hours,
            'concern' => $appointment->concern,
            'notes' => $appointment->notes,
            'status' => $appointment->status,
        ];

        try {
            $data = app(AppointmentScheduleGuard::class)->assertSchedulable($data, $appointment);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return redirect()
                ->back()
                ->withErrors($exception->errors());
        }

        $warnings = $data['_schedule_warnings'] ?? [];
        unset($data['_schedule_warnings']);

        $previousStarts = $appointment->starts_at?->toIso8601String();

        $appointment->fill([
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'technician_user_id' => $data['technician_user_id'],
            'workstation_id' => $data['workstation_id'],
        ])->save();

        app(RecordAppointmentFact::class)->execute(
            $appointment,
            \App\Ark\Operations\Events\OperationalEventName::AppointmentRescheduled,
            $request->user(),
            ['previous_starts_at' => $previousStarts],
        );

        $appointment->load(['customer', 'vehicle', 'advisor', 'technician', 'workstation', 'repairOrder']);

        $card = app(AppointmentScheduleRowPresenter::class)->present($appointment, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'appointment' => $card,
                'message' => 'Appointment moved.',
                'warnings' => $warnings,
            ]);
        }

        $redirect = redirect()
            ->route('operations.appointments.index', ['day' => $appointment->starts_at->toDateString()])
            ->with('status', 'Appointment moved.');

        if ($warnings !== []) {
            $redirect->with('schedule_warnings', $warnings);
        }

        return $redirect;
    }
}
