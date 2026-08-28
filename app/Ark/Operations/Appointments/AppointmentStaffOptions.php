<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Collection;

final class AppointmentStaffOptions
{
    /**
     * @return Collection<int, User>
     */
    public function advisors(): Collection
    {
        return User::query()
            ->active()
            ->role([ArkRole::Advisor->value, ArkRole::Admin->value])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, User>
     */
    public function technicians(): Collection
    {
        return User::query()
            ->active()
            ->role(ArkRole::Technician->value)
            ->orderBy('name')
            ->get(['id', 'name', 'scheduling_hours']);
    }

    /**
     * Eligible technicians for the select, plus the appointment's current technician when inactive/removed from role.
     *
     * @return Collection<int, User>
     */
    public function techniciansForAppointmentSelect(?User $current = null): Collection
    {
        $technicians = $this->technicians();

        if ($current === null) {
            return $technicians;
        }

        if ($technicians->contains(fn (User $technician): bool => (int) $technician->id === (int) $current->id)) {
            return $technicians;
        }

        return $technicians
            ->push($current)
            ->sortBy('name')
            ->values();
    }

    /**
     * @return Collection<int, Workstation>
     */
    public function schedulableWorkstations(): Collection
    {
        return Workstation::query()
            ->where('is_active', true)
            ->where('accepts_scheduled_work', true)
            ->get(['id', 'name', 'location_label'])
            ->sortBy(fn (Workstation $bay): string => $bay->displayLocation(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Schedulable bays for the select, plus the appointment's current bay when it was removed from the schedule.
     *
     * @return Collection<int, Workstation>
     */
    public function workstationsForAppointmentSelect(?Workstation $current = null): Collection
    {
        $workstations = $this->schedulableWorkstations();

        if ($current === null) {
            return $workstations;
        }

        if ($workstations->contains(fn (Workstation $bay): bool => (int) $bay->id === (int) $current->id)) {
            return $workstations;
        }

        return $workstations
            ->push($current)
            ->sortBy(fn (Workstation $bay): string => $bay->displayLocation(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
