<?php

namespace App\Ark\Operations\Booking;

/**
 * What the customer has chosen so far: the service, how many vehicles, and where the work happens.
 * Availability and submission evaluate the same context, so the form never offers what submit refuses.
 */
final class BookingContext
{
    /**
     * @param  array{line1?: string, line2?: string, city?: string, state?: string, postal_code?: string, notes?: string}  $address
     */
    public function __construct(
        public readonly ?BookingService $service = null,
        public readonly ?string $option = null,
        public readonly int $vehicleCount = 1,
        public readonly ServiceLocationType $location = ServiceLocationType::Shop,
        public readonly array $address = [],
    ) {}

    public function durationMinutes(): int
    {
        $perVehicle = $this->service?->durationFor($this->option) ?? 60;

        return max(15, min(480, $perVehicle * max(1, $this->vehicleCount)));
    }

    public function workloadHours(): float
    {
        $perVehicle = $this->service?->workloadHoursFor($this->option) ?? 1.0;

        return round($perVehicle * max(1, $this->vehicleCount), 2);
    }

    public function postalCode(): ?string
    {
        $code = trim((string) ($this->address['postal_code'] ?? ''));

        return $code === '' ? null : $code;
    }

    /**
     * Instant only when the shop chose it and this service does not insist on approval.
     */
    public function mode(array $settings): BookingMode
    {
        $mode = BookingMode::tryFrom((string) ($settings['mode'] ?? '')) ?? BookingMode::Request;

        if ($mode === BookingMode::Instant && $this->service?->requires_approval) {
            return BookingMode::Request;
        }

        return $mode;
    }
}
