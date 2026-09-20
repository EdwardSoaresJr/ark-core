<?php

namespace App\Ark\Operations\Appointments;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Schedule day control: enable/disable public appointment requests for one date.
 */
final class AppointmentRequestExceptionController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'mode' => ['required', Rule::in([
                AppointmentRequestException::MODE_ENABLE,
                AppointmentRequestException::MODE_DISABLE,
                'clear',
            ])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $date = $data['date'];
        $redirect = redirect()->route('operations.appointments.index', array_filter([
            'day' => $date,
            'view' => $request->filled('view') ? (string) $request->string('view') : null,
            'lens' => $request->filled('lens') ? (string) $request->string('lens') : null,
        ]));

        if ($data['mode'] === 'clear') {
            AppointmentRequestException::query()->whereDate('date', $date)->delete();

            return $redirect->with('status', 'Appointment request override cleared for this day.');
        }

        AppointmentRequestException::query()->updateOrCreate(
            ['date' => $date],
            [
                'mode' => $data['mode'],
                'reason' => filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null,
                'created_by_user_id' => $request->user()?->id,
            ],
        );

        $status = $data['mode'] === AppointmentRequestException::MODE_DISABLE
            ? 'Public appointment requests disabled for this day.'
            : 'Public appointment requests enabled for this day.';

        return $redirect->with('status', $status);
    }
}
