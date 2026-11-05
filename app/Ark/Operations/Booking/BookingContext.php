<?php

namespace App\Ark\Operations\Booking;

/**
 * What the customer has chosen so far, as the scheduler needs it: how long the visit is, how much
 * work it is, how many vehicles, where it happens, and whether the booking product insists on staff
 * approval. Availability and submission evaluate the same context, so the form never offers what
 * submit refuses. The booking product decides these from its own catalog.
 */
final class BookingContext
{
    /**
     * @param  array{line1?: string, line2?: string, city?: string, state?: string, postal_code?: string, notes?: string}  $address
     */
    public function __construct(
        public readonly ?string $serviceName = null,
        public readonly ?string $option = null,
        public readonly int $perVehicleMinutes = 60,
        public readonly ?float $perVehicleHours = null,
        public readonly int $vehicleCount = 1,
        public readonly ServiceLocationType $location = ServiceLocationType::Shop,
        public readonly array $address = [],
        public readonly bool $requiresApproval = false,
        public readonly ?string $serviceReference = null,
    ) {}

    public function durationMinutes(): int
    {
        return max(15, min(480, max(15, $this->perVehicleMinutes) * max(1, $this->vehicleCount)));
    }

    public function workloadHours(): float
    {
        $perVehicle = $this->perVehicleHours ?? round(max(15, $this->perVehicleMinutes) / 60, 2);

        return round($perVehicle * max(1, $this->vehicleCount), 2);
    }

    public function postalCode(): ?string
    {
        $code = trim((string) ($this->address['postal_code'] ?? ''));

        return $code === '' ? null : $code;
    }

    /**
     * Instant only when the shop's policy allows it and this booking does not insist on approval.
     *
     * @param  array<string, mixed>  $settings  BookingSettings::forShop()
     */
    public function mode(array $settings): BookingMode
    {
        $mode = BookingMode::tryFrom((string) ($settings['mode'] ?? '')) ?? BookingMode::Request;

        return $mode === BookingMode::Instant && $this->requiresApproval ? BookingMode::Request : $mode;
    }

    public function serviceLabel(): ?string
    {
        if ($this->serviceName === null || trim($this->serviceName) === '') {
            return null;
        }

        return $this->option !== null && trim($this->option) !== '' ? "{$this->serviceName} ({$this->option})" : $this->serviceName;
    }
}
