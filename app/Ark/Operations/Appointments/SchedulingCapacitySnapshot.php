<?php

namespace App\Ark\Operations\Appointments;

/**
 * Disposable capacity answer for one open period (typically one shop day).
 * Rebuild from appointments + hours + shop policy — never store as authority.
 */
final class SchedulingCapacitySnapshot
{
    public function __construct(
        public readonly string $date,
        public readonly bool $available,
        public readonly float $scheduledHours,
        public readonly ?float $baseCapacityHours,
        public readonly ?float $targetCapacityHours,
        public readonly int $targetPercent,
        public readonly AppointmentCapacityBasis $basis,
        public readonly AppointmentCapacityEnforcement $enforcement,
        public readonly ?float $technicianCapacityHours,
        public readonly ?float $bayCapacityHours,
        public readonly string $basisUsed,
        public readonly ?string $unavailableReason = null,
    ) {}

    public function hoursOverBase(): float
    {
        if (! $this->available || $this->baseCapacityHours === null) {
            return 0.0;
        }

        return round(max(0, $this->scheduledHours - $this->baseCapacityHours), 2);
    }

    public function hoursRemainingToTarget(): ?float
    {
        if (! $this->available || $this->targetCapacityHours === null) {
            return null;
        }

        return round($this->targetCapacityHours - $this->scheduledHours, 2);
    }

    public function hoursBeyondTarget(): float
    {
        if (! $this->available || $this->targetCapacityHours === null) {
            return 0.0;
        }

        return round(max(0, $this->scheduledHours - $this->targetCapacityHours), 2);
    }

    public function isOverBase(): bool
    {
        return $this->available
            && $this->baseCapacityHours !== null
            && $this->scheduledHours > $this->baseCapacityHours;
    }

    public function isBeyondTarget(): bool
    {
        return $this->available
            && $this->targetCapacityHours !== null
            && $this->scheduledHours > $this->targetCapacityHours;
    }

    public function isWithinTarget(): bool
    {
        return $this->available && ! $this->isBeyondTarget();
    }

    /**
     * @return 'unavailable'|'below_base'|'overpacked'|'beyond_target'
     */
    public function status(): string
    {
        if (! $this->available) {
            return 'unavailable';
        }

        if ($this->isBeyondTarget()) {
            return 'beyond_target';
        }

        if ($this->isOverBase()) {
            return 'overpacked';
        }

        return 'below_base';
    }

    public function withScheduledHours(float $scheduledHours): self
    {
        return new self(
            date: $this->date,
            available: $this->available,
            scheduledHours: round($scheduledHours, 2),
            baseCapacityHours: $this->baseCapacityHours,
            targetCapacityHours: $this->targetCapacityHours,
            targetPercent: $this->targetPercent,
            basis: $this->basis,
            enforcement: $this->enforcement,
            technicianCapacityHours: $this->technicianCapacityHours,
            bayCapacityHours: $this->bayCapacityHours,
            basisUsed: $this->basisUsed,
            unavailableReason: $this->unavailableReason,
        );
    }
}
