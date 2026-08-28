<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\Workstation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class AppointmentScheduleGuard
{
    public function __construct(
        private readonly AppointmentConflictDetector $conflicts,
        private readonly SchedulingCapacityCalculator $capacity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function assertSchedulable(array $data, ?Appointment $existing = null): array
    {
        $startsAt = ShopDisplayTimezone::parseLocal((string) $data['starts_at']);
        $endsAt = ShopDisplayTimezone::parseLocal((string) $data['ends_at']);

        $technicianUserId = isset($data['technician_user_id'])
            ? (int) $data['technician_user_id']
            : null;
        if ($technicianUserId === 0) {
            $technicianUserId = null;
        }

        $workstationId = isset($data['workstation_id'])
            ? (int) $data['workstation_id']
            : null;
        if ($workstationId === 0) {
            $workstationId = null;
        }

        $this->assertOptionalWorkstation($workstationId, $existing);
        $this->assertWithinHours($startsAt, $endsAt, $technicianUserId);

        $warnings = $this->assignmentOverlapWarnings(
            $startsAt->copy()->utc(),
            $endsAt->copy()->utc(),
            $technicianUserId,
            $workstationId,
            $existing?->id,
        );

        $estimated = array_key_exists('estimated_labor_hours', $data) && $data['estimated_labor_hours'] !== '' && $data['estimated_labor_hours'] !== null
            ? (float) $data['estimated_labor_hours']
            : null;

        $workload = $this->capacity->workloadHoursFromInput($estimated, $startsAt, $endsAt);
        $proposed = $this->capacity->forDayWithProposedWorkload($startsAt, $workload, $existing);

        if ($proposed->available && $proposed->isBeyondTarget()) {
            $beyond = $proposed->hoursBeyondTarget();
            $dayLabel = ShopDisplayTimezone::format($startsAt, 'l') ?? 'this day';
            $message = "This appointment would place {$dayLabel} {$beyond} hours beyond the shop's scheduling target.";

            if ($proposed->enforcement === AppointmentCapacityEnforcement::Block) {
                throw ValidationException::withMessages([
                    'estimated_labor_hours' => $message,
                ]);
            }

            $warnings[] = $message;
        } elseif ($proposed->available && $proposed->isOverBase()) {
            $over = $proposed->hoursOverBase();
            $warnings[] = "This day would be over base capacity by {$over} hours (within the {$proposed->targetPercent}% scheduling target).";
        }

        // MySQL datetime casts store wall-clock of the Carbon instance — persist UTC.
        $data['starts_at'] = $startsAt->copy()->utc();
        $data['ends_at'] = $endsAt->copy()->utc();
        $data['technician_user_id'] = $technicianUserId;
        $data['workstation_id'] = $workstationId;

        if (array_key_exists('estimated_labor_hours', $data) && $data['estimated_labor_hours'] === '') {
            $data['estimated_labor_hours'] = null;
        }

        $data['_schedule_warnings'] = $warnings;

        return $data;
    }

    /**
     * @return list<string>
     */
    private function assignmentOverlapWarnings(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $technicianUserId,
        ?int $workstationId,
        ?int $ignoreAppointmentId,
    ): array {
        $conflicts = $this->conflicts->conflicts(
            $startsAt,
            $endsAt,
            $technicianUserId,
            $workstationId,
            $ignoreAppointmentId,
        );

        if ($conflicts->isEmpty()) {
            return [];
        }

        $labels = $conflicts->map(function (Appointment $appointment): string {
            $who = $appointment->customer?->name ?? 'Customer';
            $when = ShopDisplayTimezone::format($appointment->starts_at, 'D g:ia') ?? 'scheduled';

            return "{$who} ({$when})";
        })->implode(', ');

        return ["Planning overlap with existing appointment(s): {$labels}."];
    }

    private function assertOptionalWorkstation(?int $workstationId, ?Appointment $existing): void
    {
        if ($workstationId === null) {
            return;
        }

        $workstation = Workstation::query()->find($workstationId);

        if ($workstation === null || ! $workstation->is_active) {
            throw ValidationException::withMessages([
                'workstation_id' => 'Choose an active bay, or leave Unassigned.',
            ]);
        }

        if ($workstation->accepts_scheduled_work) {
            return;
        }

        if ($existing !== null && (int) $existing->workstation_id === $workstationId) {
            return;
        }

        throw ValidationException::withMessages([
            'workstation_id' => 'Choose a bay that is on the schedule, or leave Unassigned.',
        ]);
    }

    private function assertWithinHours(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $technicianUserId,
    ): void {
        $shopHours = ShopSettings::current()->schedulingHours();

        if (! SchedulingHours::contains($shopHours, $startsAt, $endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Appointment must fall within shop scheduling hours.',
            ]);
        }

        if ($technicianUserId === null) {
            return;
        }

        $technician = User::query()->find($technicianUserId);
        $techHours = $technician?->scheduling_hours !== null && $technician->scheduling_hours !== []
            ? SchedulingHours::normalize($technician->scheduling_hours)
            : $shopHours;

        if (! SchedulingHours::contains($techHours, $startsAt, $endsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Appointment must fall within the technician\'s scheduling hours.',
            ]);
        }
    }
}
