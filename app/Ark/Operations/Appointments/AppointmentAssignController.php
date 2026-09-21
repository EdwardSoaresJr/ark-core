<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Updates technician / bay assignment only — same appointment fields as reschedule.
 * Does not move the appointment in time.
 */
class AppointmentAssignController
{
    public function __invoke(Request $request, Appointment $appointment): RedirectResponse
    {
        if ($appointment->status === AppointmentStatus::Canceled) {
            abort(422, 'Canceled appointments cannot be assigned.');
        }

        $validated = $request->validate([
            'technician_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'workstation_id' => ['nullable', 'integer', 'exists:workstations,id'],
            'day' => ['nullable', 'date'],
            'view' => ['nullable', 'string'],
            'allocate' => ['nullable', 'string'],
            'lens' => ['nullable', 'string'],
        ]);

        $technicianUserId = array_key_exists('technician_user_id', $validated)
            ? (isset($validated['technician_user_id']) ? (int) $validated['technician_user_id'] : null)
            : ($appointment->technician_user_id !== null ? (int) $appointment->technician_user_id : null);

        $workstationId = array_key_exists('workstation_id', $validated)
            ? (isset($validated['workstation_id']) ? (int) $validated['workstation_id'] : null)
            : ($appointment->workstation_id !== null ? (int) $appointment->workstation_id : null);

        try {
            $data = app(AppointmentScheduleGuard::class)->assertAssignmentChange(
                $appointment,
                $technicianUserId,
                $workstationId,
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->back()
                ->withErrors($exception->errors());
        }

        $warnings = $data['_schedule_warnings'] ?? [];
        unset($data['_schedule_warnings']);

        $appointment->fill([
            'technician_user_id' => $data['technician_user_id'],
            'workstation_id' => $data['workstation_id'],
        ])->save();

        $redirectDay = filled($validated['day'] ?? null)
            ? (string) $validated['day']
            : $appointment->starts_at?->toDateString();

        $redirect = redirect()
            ->route('operations.appointments.index', array_filter([
                'day' => $redirectDay,
                'view' => filled($validated['view'] ?? null) ? (string) $validated['view'] : 'week',
                'allocate' => filled($validated['allocate'] ?? null) ? (string) $validated['allocate'] : null,
                'lens' => filled($validated['lens'] ?? null) ? (string) $validated['lens'] : null,
            ]))
            ->with('status', 'Assignment updated.');

        if ($warnings !== []) {
            $redirect->with('schedule_warnings', $warnings);
        }

        return $redirect;
    }
}
