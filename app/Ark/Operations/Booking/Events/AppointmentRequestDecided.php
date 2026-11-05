<?php

namespace App\Ark\Operations\Booking\Events;

use App\Ark\Operations\Booking\AppointmentRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Staff scheduled or declined a pending request. Core sends nothing; ARK Shop may tell the customer.
 */
final class AppointmentRequestDecided
{
    use Dispatchable;

    public function __construct(public readonly int $appointmentRequestId) {}

    public function request(): ?AppointmentRequest
    {
        return AppointmentRequest::query()->find($this->appointmentRequestId);
    }
}
