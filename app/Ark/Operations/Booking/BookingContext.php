<?php

namespace App\Ark\Operations\Booking;

/**
 * What the customer has chosen so far, as the scheduler needs it: how long the visit is, how much
 * work it is, how many vehicles, where it happens, and whether the booking product insists on staff
 * approval. Availability and submission evaluate the same context, so the form never offers what
 * submit refuses. The booking product decides these from its own catalog, or hands Core the visit's
 * work items and lets forWork() combine them under the shop's visit length policy.
 */
final class BookingContext
{
    /**
     * @param  array{line1?: string, line2?: string, city?: string, state?: string, postal_code?: string, notes?: string}  $address
     * @param  list<BookingWorkItem>  $work
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
        public readonly array $work = [],
    ) {}

    public const MAX_MINUTES = 480;

    /**
     * One visit for several work items. Drop-off visits last as long as the longest item; sequential
     * visits (the customer waits, or a mobile technician works through the list) add them up. The
     * workload is always the sum of the items' labor. A visit longer than a day is never confirmed
     * online: it goes to staff as a request.
     *
     * @param  list<BookingWorkItem>  $work
     * @param  array{line1?: string, line2?: string, city?: string, state?: string, postal_code?: string, notes?: string}  $address
     * @param  array<string, mixed>|null  $settings  BookingSettings::forShop()
     */
    public static function forWork(
        array $work,
        int $vehicleCount = 1,
        ServiceLocationType $location = ServiceLocationType::Shop,
        array $address = [],
        bool $requiresApproval = false,
        ?array $settings = null,
    ): self {
        $settings ??= BookingSettings::forShop();
        $work = array_values(array_filter($work, fn (mixed $item): bool => $item instanceof BookingWorkItem));
        $minutes = array_map(fn (BookingWorkItem $item): int => $item->visitMinutes(), $work) ?: [BookingWorkItem::DEFAULT_MINUTES];
        $visit = ($settings['visit_length'] ?? null) === BookingSettings::VISIT_SEQUENTIAL ? array_sum($minutes) : max($minutes);
        $hours = $work === [] ? null : round(array_sum(array_map(fn (BookingWorkItem $item): float => $item->workloadHours(), $work)), 2);
        $single = count($work) === 1 ? $work[0] : null;
        $names = implode(', ', array_map(fn (BookingWorkItem $item): string => $item->name, $work));

        return new self(
            serviceName: $names !== '' ? mb_substr($names, 0, 255) : null,
            option: $single?->option,
            perVehicleMinutes: (int) $visit,
            perVehicleHours: $hours,
            vehicleCount: $vehicleCount,
            location: $location,
            address: $address,
            requiresApproval: $requiresApproval
                || $visit * max(1, $vehicleCount) > self::MAX_MINUTES
                || array_filter($work, fn (BookingWorkItem $item): bool => $item->requiresApproval) !== [],
            serviceReference: $single?->reference,
            work: $work,
        );
    }

    public function durationMinutes(): int
    {
        return max(15, min(self::MAX_MINUTES, max(15, $this->perVehicleMinutes) * max(1, $this->vehicleCount)));
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
        if (count($this->work) > 1) {
            return implode(' + ', array_map(fn (BookingWorkItem $item): string => $item->label(), $this->work));
        }

        if ($this->serviceName === null || trim($this->serviceName) === '') {
            return null;
        }

        return $this->option !== null && trim($this->option) !== '' ? "{$this->serviceName} ({$this->option})" : $this->serviceName;
    }
}
