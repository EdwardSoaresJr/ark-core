<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Support\Carbon;

final class AppointmentsBoardProjection
{
    /**
     * @return list<array<string, mixed>>
     */
    public function comingInOn(Carbon $day): array
    {
        $start = $day->copy()->timezone(ShopDisplayTimezone::resolve())->startOfDay()->utc();
        $end = $day->copy()->timezone(ShopDisplayTimezone::resolve())->endOfDay()->utc();

        return Appointment::query()
            ->with(['repairOrder:id,repair_order_id,status,assigned_technician_id', 'repairOrder.assignedTechnician:id,name', 'customer:id,first_name,last_name', 'vehicle:id,year,make,model'])
            ->whereBetween('starts_at', [$start, $end])
            ->whereIn('status', [
                AppointmentStatus::Scheduled->value,
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Arrived->value,
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $appointment): array => $this->row($appointment))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function upcoming(int $limit = 20): array
    {
        return Appointment::query()
            ->with(['repairOrder:id,repair_order_id,status,assigned_technician_id', 'repairOrder.assignedTechnician:id,name', 'customer:id,first_name,last_name', 'vehicle:id,year,make,model'])
            ->where('starts_at', '>=', now()->utc()->startOfDay())
            ->whereIn('status', [
                AppointmentStatus::Scheduled->value,
                AppointmentStatus::Confirmed->value,
            ])
            ->orderBy('starts_at')
            ->limit($limit)
            ->get()
            ->map(fn (Appointment $appointment): array => $this->row($appointment))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(Appointment $appointment): array
    {
        $local = $appointment->starts_at !== null
            ? ShopDisplayTimezone::present($appointment->starts_at)
            : null;
        $vehicle = $appointment->vehicle;

        return [
            'appointment_id' => $appointment->id,
            'appointment_status' => $appointment->status->value,
            'appointment_status_label' => $appointment->status->label(),
            'kind' => $appointment->kind?->value,
            'starts_at' => $appointment->starts_at?->toIso8601String(),
            'when_label' => $local?->format('D M j · g:i A'),
            'time_label' => $local?->format('g:i A'),
            'repair_order_id' => $appointment->repairOrder?->repair_order_id,
            'repair_order_status' => $appointment->repairOrder?->status?->value,
            'repair_order_status_label' => $appointment->repairOrder?->status?->label(),
            'customer_label' => $appointment->displayName(),
            'vehicle_label' => $vehicle === null
                ? null
                : trim(implode(' ', array_filter([$vehicle->year, $vehicle->make, $vehicle->model]))),
            'technician_label' => $appointment->repairOrder?->assignedTechnician?->name
                ?? $appointment->technician?->name,
            'open_in_ark_url' => $appointment->repairOrder?->repair_order_id
                ? url('/app/repair-orders/'.$appointment->repairOrder->repair_order_id)
                : null,
        ];
    }
}
