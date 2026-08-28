<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Support\Carbon;

/**
 * Capacity rail — packages SchedulingCapacityCalculator once per render.
 * Per-resource bars remain optional planning detail; shop soft capacity is primary.
 */
final class OperationalCapacityProjection
{
    public function __construct(
        private readonly SchedulingCapacityCalculator $calculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolve(Carbon $focusDay, string $view = 'day'): array
    {
        $view = in_array($view, ['day', 'week'], true) ? $view : 'day';
        $timezone = ShopDisplayTimezone::resolve();
        $focus = Carbon::parse($focusDay->toDateString(), $timezone)->startOfDay();

        if ($view === 'week') {
            return $this->resolveWeek($focus);
        }

        $snapshot = $this->calculator->forDay($focus);

        return [
            'ready' => true,
            'label' => 'Capacity',
            'focus_date' => $snapshot->date,
            'focus_label' => $focus->format('D, M j'),
            'shop' => $this->shopPayload($snapshot),
            'resources' => [],
            'technicians' => [],
            'warnings' => $this->shopWarnings($snapshot),
            'weekly' => null,
            'hint' => $snapshot->available ? null : ($snapshot->unavailableReason ?? 'Capacity unavailable'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveWeek(Carbon $focus): array
    {
        $rangeStart = $focus->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekly = [];
        $totalScheduled = 0.0;
        $totalBase = 0.0;
        $availableDays = 0;

        for ($i = 0; $i < 7; $i++) {
            $day = $rangeStart->copy()->addDays($i);
            $snapshot = $this->calculator->forDay($day);
            $totalScheduled += $snapshot->scheduledHours;
            if ($snapshot->available && $snapshot->baseCapacityHours !== null) {
                $totalBase += $snapshot->baseCapacityHours;
                $availableDays++;
            }
            $ratio = ($snapshot->available && ($snapshot->baseCapacityHours ?? 0) > 0)
                ? min(1.5, $snapshot->scheduledHours / $snapshot->baseCapacityHours)
                : ($snapshot->scheduledHours > 0 ? 1.5 : 0.0);
            $weekly[] = [
                'date' => $day->toDateString(),
                'day_label' => $day->format('D j'),
                'assigned_hours' => $snapshot->scheduledHours,
                'capacity_hours' => $snapshot->baseCapacityHours ?? 0.0,
                'target_hours' => $snapshot->targetCapacityHours,
                'ratio' => $ratio,
                'status' => $snapshot->status(),
            ];
        }

        $weekSnapshot = $this->calculator->forDay($focus);
        $aggregateAvailable = $availableDays > 0;
        $aggregate = new SchedulingCapacitySnapshot(
            date: $rangeStart->toDateString(),
            available: $aggregateAvailable,
            scheduledHours: round($totalScheduled, 2),
            baseCapacityHours: $aggregateAvailable ? round($totalBase, 2) : null,
            targetCapacityHours: $aggregateAvailable
                ? round($totalBase * ($weekSnapshot->targetPercent / 100), 2)
                : null,
            targetPercent: $weekSnapshot->targetPercent,
            basis: $weekSnapshot->basis,
            enforcement: $weekSnapshot->enforcement,
            technicianCapacityHours: null,
            bayCapacityHours: null,
            basisUsed: $weekSnapshot->basisUsed,
            unavailableReason: $aggregateAvailable ? null : 'Capacity unavailable',
        );

        return [
            'ready' => true,
            'label' => 'Capacity',
            'focus_date' => $focus->toDateString(),
            'focus_label' => 'Week of '.$rangeStart->format('M j, Y'),
            'shop' => $this->shopPayload($aggregate),
            'resources' => [],
            'technicians' => [],
            'warnings' => $this->shopWarnings($aggregate),
            'weekly' => $weekly,
            'hint' => $aggregate->available ? null : ($aggregate->unavailableReason ?? 'Capacity unavailable'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shopPayload(SchedulingCapacitySnapshot $snapshot): array
    {
        $remaining = $snapshot->hoursRemainingToTarget();

        return [
            'available' => $snapshot->available,
            'status' => $snapshot->status(),
            'scheduled_hours' => $snapshot->scheduledHours,
            'base_hours' => $snapshot->baseCapacityHours,
            'target_hours' => $snapshot->targetCapacityHours,
            'target_percent' => $snapshot->targetPercent,
            'basis' => $snapshot->basis->value,
            'basis_used' => $snapshot->basisUsed,
            'enforcement' => $snapshot->enforcement->value,
            'hours_over_base' => $snapshot->hoursOverBase(),
            'hours_beyond_target' => $snapshot->hoursBeyondTarget(),
            'hours_remaining_to_target' => $remaining,
            'scheduled_label' => $this->hoursLabel($snapshot->scheduledHours),
            'base_label' => $snapshot->baseCapacityHours !== null ? $this->hoursLabel($snapshot->baseCapacityHours) : null,
            'target_label' => $snapshot->targetCapacityHours !== null ? $this->hoursLabel($snapshot->targetCapacityHours) : null,
            'status_copy' => $this->statusCopy($snapshot),
            'unavailable_reason' => $snapshot->unavailableReason,
        ];
    }

    /**
     * @return list<string>
     */
    private function shopWarnings(SchedulingCapacitySnapshot $snapshot): array
    {
        if (! $snapshot->available) {
            return [
                'Capacity unavailable. Appointments can still be scheduled.',
            ];
        }

        if ($snapshot->isBeyondTarget()) {
            return [
                'Beyond scheduling target by '.$this->hoursLabel($snapshot->hoursBeyondTarget()).'.',
            ];
        }

        if ($snapshot->isOverBase()) {
            return [
                'Over base capacity by '.$this->hoursLabel($snapshot->hoursOverBase()).'. Within the shop’s '.$snapshot->targetPercent.'% target.',
            ];
        }

        return [];
    }

    private function statusCopy(SchedulingCapacitySnapshot $snapshot): string
    {
        if (! $snapshot->available) {
            return 'Capacity unavailable. Appointments can still be scheduled.';
        }

        if ($snapshot->isBeyondTarget()) {
            return 'Beyond scheduling target by '.$this->hoursLabel($snapshot->hoursBeyondTarget()).'.';
        }

        if ($snapshot->isOverBase()) {
            $remaining = $snapshot->hoursRemainingToTarget();

            return 'Over base capacity by '.$this->hoursLabel($snapshot->hoursOverBase()).'.'
                .($remaining !== null && $remaining >= 0
                    ? ' '.$this->hoursLabel($remaining).' remaining to target.'
                    : '');
        }

        $remaining = $snapshot->hoursRemainingToTarget();

        return $remaining !== null
            ? $this->hoursLabel(max(0, $remaining)).' remaining to target.'
            : 'Within base capacity.';
    }

    private function hoursLabel(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.').' hrs';
    }
}
