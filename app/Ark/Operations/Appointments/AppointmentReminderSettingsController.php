<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentReminderSettingsController
{
    /** @var list<int> */
    public const HOURS_OPTIONS = [1, 2, 3, 4];

    public function __invoke(Request $request, Appointment $appointment): RedirectResponse
    {
        if ($appointment->status === AppointmentStatus::Canceled) {
            abort(422, 'Canceled appointments cannot schedule reminders.');
        }

        $data = $request->validate([
            'reminder_day_before' => ['nullable', 'boolean'],
            'reminder_hours_before' => ['nullable', 'integer', Rule::in(self::HOURS_OPTIONS)],
        ]);

        $hours = $request->filled('reminder_hours_before')
            ? (int) $data['reminder_hours_before']
            : null;

        $appointment->forceFill([
            'reminder_day_before' => $request->boolean('reminder_day_before'),
            'reminder_hours_before' => $hours,
        ])->save();

        return redirect()
            ->route('operations.appointments.show', $appointment)
            ->with('status', 'Reminder plan saved.');
    }
}
