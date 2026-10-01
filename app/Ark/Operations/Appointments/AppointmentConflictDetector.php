<?php

namespace App\Ark\Operations\Appointments;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AppointmentConflictDetector
{
    /**
     * Active appointments that overlap the same technician or workstation.
     *
     * @return Collection<int, Appointment>
     */
    public function conflicts(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $technicianUserId,
        ?int $workstationId,
        ?int $ignoreAppointmentId = null,
    ): Collection {
        if ($technicianUserId === null && $workstationId === null) {
            return collect();
        }

        $active = array_map(
            static fn (AppointmentStatus $status): string => $status->value,
            AppointmentStatus::activeToday(),
        );

        return Appointment::query()
            ->with('customer')
            ->whereIn('status', $active)
            ->when($ignoreAppointmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->where(function ($query) use ($technicianUserId, $workstationId): void {
                if ($technicianUserId !== null) {
                    $query->where('technician_user_id', $technicianUserId);
                }

                if ($workstationId !== null) {
                    if ($technicianUserId !== null) {
                        $query->orWhere('workstation_id', $workstationId);
                    } else {
                        $query->where('workstation_id', $workstationId);
                    }
                }
            })
            ->orderBy('starts_at')
            ->get();
    }
}
