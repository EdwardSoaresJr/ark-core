<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for soft shop-capacity scheduling math.
 */
final class SchedulingCapacityCalculator
{
    public function __construct(
        private readonly AppointmentStaffOptions $staff,
    ) {}

    public function forDay(CarbonInterface $day, ?Appointment $exclude = null): SchedulingCapacitySnapshot
    {
        $timezone = ShopDisplayTimezone::resolve();
        $focus = Carbon::parse($day->toDateString(), $timezone)->startOfDay();
        $settings = ShopSettings::current();
        $basis = AppointmentCapacityBasis::tryFrom((string) ($settings->appointment_capacity_basis ?? ''))
            ?? AppointmentCapacityBasis::LimitingResource;
        $targetPercent = max(25, min(300, (int) ($settings->appointment_scheduling_target_percent ?? 100)));
        $enforcement = AppointmentCapacityEnforcement::tryFrom((string) ($settings->appointment_capacity_enforcement ?? ''))
            ?? AppointmentCapacityEnforcement::Warn;

        $shopHours = $settings->schedulingHours();
        $shopOpenHours = $this->openHoursOnDay($focus, $shopHours);

        $technicianCapacity = $this->technicianCapacityHours($focus, $shopHours);
        $bayCapacity = $this->bayCapacityHours($shopOpenHours);

        [$base, $basisUsed, $unavailableReason] = $this->resolveBase(
            $basis,
            $technicianCapacity,
            $bayCapacity,
        );

        $scheduled = $this->scheduledWorkloadHours($focus, $exclude);

        if ($base === null) {
            return new SchedulingCapacitySnapshot(
                date: $focus->toDateString(),
                available: false,
                scheduledHours: $scheduled,
                baseCapacityHours: null,
                targetCapacityHours: null,
                targetPercent: $targetPercent,
                basis: $basis,
                enforcement: $enforcement,
                technicianCapacityHours: $technicianCapacity,
                bayCapacityHours: $bayCapacity,
                basisUsed: $basisUsed,
                unavailableReason: $unavailableReason ?? 'Capacity unavailable',
            );
        }

        $target = round($base * ($targetPercent / 100), 2);

        return new SchedulingCapacitySnapshot(
            date: $focus->toDateString(),
            available: true,
            scheduledHours: $scheduled,
            baseCapacityHours: $base,
            targetCapacityHours: $target,
            targetPercent: $targetPercent,
            basis: $basis,
            enforcement: $enforcement,
            technicianCapacityHours: $technicianCapacity,
            bayCapacityHours: $bayCapacity,
            basisUsed: $basisUsed,
        );
    }

    /**
     * Snapshot after adding (or replacing) workload for create/update enforcement.
     */
    public function forDayWithProposedWorkload(
        CarbonInterface $day,
        float $proposedWorkloadHours,
        ?Appointment $exclude = null,
    ): SchedulingCapacitySnapshot {
        $snapshot = $this->forDay($day, $exclude);

        if (! $snapshot->available) {
            return $snapshot;
        }

        return $snapshot->withScheduledHours($snapshot->scheduledHours + max(0, $proposedWorkloadHours));
    }

    public function workloadHoursForAppointment(Appointment $appointment): float
    {
        return $this->laborHours($appointment);
    }

    public function workloadHoursFromInput(?float $estimatedLaborHours, CarbonInterface $startsAt, CarbonInterface $endsAt): float
    {
        if ($estimatedLaborHours !== null && $estimatedLaborHours > 0) {
            return round($estimatedLaborHours, 2);
        }

        return max(0.25, round($startsAt->diffInMinutes($endsAt) / 60, 2));
    }

    /**
     * @return array{0: ?float, 1: string, 2: ?string}
     */
    private function resolveBase(
        AppointmentCapacityBasis $basis,
        ?float $technicianCapacity,
        ?float $bayCapacity,
    ): array {
        $hasTech = $technicianCapacity !== null && $technicianCapacity > 0;
        $hasBay = $bayCapacity !== null && $bayCapacity > 0;

        return match ($basis) {
            AppointmentCapacityBasis::Technicians => $hasTech
                ? [$technicianCapacity, 'technicians', null]
                : ($hasBay
                    ? [$bayCapacity, 'bays', null]
                    : [null, 'none', 'Capacity unavailable — add technicians or bays under Settings → Appointments.']),
            AppointmentCapacityBasis::Bays => $hasBay
                ? [$bayCapacity, 'bays', null]
                : ($hasTech
                    ? [$technicianCapacity, 'technicians', null]
                    : [null, 'none', 'Capacity unavailable — add bays or technicians under Settings → Appointments.']),
            AppointmentCapacityBasis::LimitingResource => match (true) {
                $hasTech && $hasBay => [min($technicianCapacity, $bayCapacity), 'limiting_resource', null],
                $hasTech => [$technicianCapacity, 'technicians', null],
                $hasBay => [$bayCapacity, 'bays', null],
                default => [null, 'none', 'Capacity unavailable — add technicians or bays under Settings → Appointments.'],
            },
        };
    }

    private function technicianCapacityHours(Carbon $day, array $shopHours): ?float
    {
        $technicians = $this->staff->technicians();
        if ($technicians->isEmpty()) {
            return null;
        }

        $total = 0.0;
        foreach ($technicians as $technician) {
            $weekly = $technician->scheduling_hours !== null && $technician->scheduling_hours !== []
                ? SchedulingHours::normalize($technician->scheduling_hours)
                : $shopHours;
            $total += $this->openHoursOnDay($day, $weekly);
        }

        return $total > 0 ? round($total, 2) : null;
    }

    private function bayCapacityHours(float $shopOpenHours): ?float
    {
        if ($shopOpenHours <= 0) {
            return null;
        }

        $bayCount = $this->staff->schedulableWorkstations()->count();
        if ($bayCount === 0) {
            return null;
        }

        return round($bayCount * $shopOpenHours, 2);
    }

    /**
     * @param  array<string, array{enabled: bool, open: string, close: string}>  $hours
     */
    public function openHoursOnDay(CarbonInterface $day, array $hours): float
    {
        $dayKey = strtolower($day->englishDayOfWeek);
        $row = $hours[$dayKey] ?? null;
        if ($row === null || ! ($row['enabled'] ?? false)) {
            return 0.0;
        }

        $open = $day->copy()->setTimeFromTimeString($row['open']);
        $close = $day->copy()->setTimeFromTimeString($row['close']);

        return max(0.0, round($open->diffInMinutes($close) / 60, 2));
    }

    private function scheduledWorkloadHours(Carbon $focus, ?Appointment $exclude): float
    {
        $appointments = $this->activeAppointmentsOnDay($focus, $exclude);

        return round($appointments->sum(fn (Appointment $appointment): float => $this->laborHours($appointment)), 2);
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function activeAppointmentsOnDay(Carbon $focus, ?Appointment $exclude): Collection
    {
        return Appointment::query()
            ->where('starts_at', '<', $focus->copy()->endOfDay()->utc())
            ->where('ends_at', '>', $focus->copy()->startOfDay()->utc())
            ->whereIn('status', array_map(
                static fn (AppointmentStatus $status): string => $status->value,
                AppointmentStatus::activeToday(),
            ))
            ->when($exclude !== null, fn ($query) => $query->whereKeyNot($exclude->id))
            ->get(['id', 'starts_at', 'ends_at', 'estimated_labor_hours', 'status']);
    }

    private function laborHours(Appointment $appointment): float
    {
        if ($appointment->estimated_labor_hours !== null) {
            return (float) $appointment->estimated_labor_hours;
        }

        return max(0.25, round($appointment->starts_at->diffInMinutes($appointment->ends_at) / 60, 2));
    }
}
