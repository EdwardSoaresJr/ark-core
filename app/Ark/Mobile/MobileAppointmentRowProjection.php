<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Models\User;

final class MobileAppointmentRowProjection
{
    /**
     * @return array<string, mixed>
     */
    public function present(Appointment $appointment, ?User $viewer = null): array
    {
        $vehicle = trim(collect([
            $appointment->vehicle?->year,
            $appointment->vehicle?->make,
            $appointment->vehicle?->model,
        ])->filter()->implode(' '));

        $displayTimezone = config('app.display_timezone');
        $startsAt = $appointment->starts_at->timezone($displayTimezone);

        return [
            'id' => $appointment->id,
            'time_label' => $startsAt->format('g:i A'),
            'ends_label' => $appointment->ends_at->timezone($displayTimezone)->format('g:i A'),
            'customer_id' => $appointment->customer_id,
            'customer_name' => $appointment->displayName(),
            'vehicle_id' => $appointment->vehicle_id,
            'vehicle_label' => $vehicle !== '' ? $vehicle : null,
            'concern' => $appointment->concern,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'advisor_label' => $appointment->advisor?->name,
            'is_mine' => $viewer !== null
                && (int) $appointment->advisor_user_id === (int) $viewer->id,
            'repair_order_id' => MobileRepairOrderRouteId::normalize(
                $appointment->repairOrder?->repair_order_id,
            ),
            'starts_at' => $startsAt->toIso8601String(),
            'status_actions' => $this->statusActions($appointment),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusActions(Appointment $appointment): array
    {
        if ($appointment->status === AppointmentStatus::Canceled) {
            return [];
        }

        return collect(AppointmentStatus::cases())
            ->reject(fn (AppointmentStatus $status): bool => $status === $appointment->status)
            ->map(fn (AppointmentStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
            ->values()
            ->all();
    }
}
