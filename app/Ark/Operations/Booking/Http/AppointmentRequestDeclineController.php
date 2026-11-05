<?php

namespace App\Ark\Operations\Booking\Http;

use App\Ark\Operations\Booking\AppointmentRequest;
use App\Ark\Operations\Booking\AppointmentRequestDecisions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AppointmentRequestDeclineController
{
    public function __invoke(Request $request, AppointmentRequest $appointmentRequest, AppointmentRequestDecisions $decisions): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'day' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $declined = $decisions->decline($appointmentRequest, $request->user(), $data['reason'] ?? null);

        return redirect()
            ->route('operations.appointments.index', array_filter(['day' => $data['day'] ?? null]))
            ->with('status', $declined
                ? 'Request from '.$appointmentRequest->contact_name.' declined. Let the customer know.'
                : 'That request was already handled.');
    }
}
