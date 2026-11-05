<?php

namespace App\Ark\Operations\Booking;

/**
 * Result of a submission. A quiet outcome (spam) shows the customer a generic receipt and saved no request.
 */
final class BookingOutcome
{
    private function __construct(
        public readonly ?AppointmentRequest $request,
        public readonly bool $created,
    ) {}

    public static function for(AppointmentRequest $request, bool $created = false): self
    {
        return new self($request, $created);
    }

    public static function quiet(): self
    {
        return new self(null, false);
    }

    public function booked(): bool
    {
        return $this->request?->mode === BookingMode::Instant && $this->request->appointment_id !== null;
    }
}
