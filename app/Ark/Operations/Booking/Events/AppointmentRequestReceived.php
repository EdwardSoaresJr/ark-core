<?php

namespace App\Ark\Operations\Booking\Events;

use App\Ark\Operations\Booking\AppointmentRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A customer sent an appointment request, or booked an instant appointment ($request->appointment_id set).
 * Core sends nothing. ARK Shop listens to notify the shop and the customer.
 */
final class AppointmentRequestReceived
{
    use Dispatchable;

    public function __construct(public readonly int $appointmentRequestId) {}

    public function request(): ?AppointmentRequest
    {
        return AppointmentRequest::query()->find($this->appointmentRequestId);
    }
}
