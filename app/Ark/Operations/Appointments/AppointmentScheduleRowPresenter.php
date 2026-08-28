<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Calendar / list card shape for Scheduling Workspace.
 * Fields reserved even when compact UI hides some — avoid layout corners later.
 */
final class AppointmentScheduleRowPresenter
{
    /**
     * @return array{
     *     id: int,
     *     time_label: string,
     *     ends_label: string,
     *     starts_at: string,
     *     ends_at: string,
     *     starts_at_iso: string,
     *     ends_at_iso: string,
     *     duration_minutes: int,
     *     customer_name: string,
     *     vehicle_label: string|null,
     *     concern: string,
     *     status: string,
     *     status_label: string,
     *     advisor_label: string|null,
     *     advisor_user_id: int|null,
     *     technician_label: string|null,
     *     technician_user_id: int|null,
     *     workstation_label: string|null,
     *     workstation_id: int|null,
     *     estimated_labor_hours: string|null,
     *     estimated_labor_label: string|null,
     *     arrival_type: string|null,
     *     arrival_type_label: string|null,
     *     is_mine: bool,
     *     customer_url: string,
     *     call_url: string|null,
     *     text_url: string|null,
     *     repair_order_url: string|null,
     *     show_url: string,
     *     create_url: string|null,
     *     day_label?: string
     * }
     */
    public function present(Appointment $appointment, ?User $viewer = null, bool $withDayLabel = false): array
    {
        $customer = trim(collect([
            $appointment->customer?->first_name,
            $appointment->customer?->last_name,
        ])->filter()->implode(' '));

        $vehicle = trim(collect([
            $appointment->vehicle?->year,
            $appointment->vehicle?->make,
            $appointment->vehicle?->model,
        ])->filter()->implode(' '));

        $isMine = $viewer !== null
            && (int) $appointment->advisor_user_id === (int) $viewer->id;

        $displayTimezone = ShopDisplayTimezone::resolve();
        $startsAt = ShopDisplayTimezone::present($appointment->starts_at);
        $endsAt = ShopDisplayTimezone::present($appointment->ends_at);
        $durationMinutes = max(15, (int) $startsAt->diffInMinutes($endsAt));

        $labor = $appointment->estimated_labor_hours;
        $laborLabel = $labor !== null
            ? rtrim(rtrim(number_format((float) $labor, 2, '.', ''), '0'), '.').'h'
            : null;

        $arrival = null;
        if ($appointment->repairOrder !== null) {
            $arrival = RepairOrderVisitMode::fromRepairOrder($appointment->repairOrder);
        }

        $workstationLabel = $appointment->workstation !== null
            ? $appointment->workstation->displayLocation()
            : null;

        $customerUrl = route('operations.customers.show', $appointment->customer_id);
        $phoneDigits = PhoneNumber::digits((string) ($appointment->customer?->phone ?? ''));
        $hasPhone = $phoneDigits !== '';

        $row = [
            'id' => $appointment->id,
            'time_label' => $startsAt->format('g:i A'),
            'ends_label' => $endsAt->format('g:i A'),
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'starts_at_iso' => $startsAt->toIso8601String(),
            'ends_at_iso' => $endsAt->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'customer_name' => $customer !== '' ? $customer : 'Unknown customer',
            'vehicle_label' => $vehicle !== '' ? $vehicle : null,
            'concern' => $appointment->concern,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'advisor_label' => $appointment->advisor?->name,
            'advisor_user_id' => $appointment->advisor_user_id !== null ? (int) $appointment->advisor_user_id : null,
            'technician_label' => $appointment->technician?->name,
            'technician_user_id' => $appointment->technician_user_id !== null ? (int) $appointment->technician_user_id : null,
            'workstation_label' => $workstationLabel,
            'workstation_id' => $appointment->workstation_id !== null ? (int) $appointment->workstation_id : null,
            'estimated_labor_hours' => $labor !== null ? (string) $labor : null,
            'estimated_labor_label' => $laborLabel,
            'arrival_type' => $arrival?->value,
            'arrival_type_label' => $arrival?->label(),
            'is_mine' => $isMine,
            'customer_url' => $customerUrl,
            'call_url' => $hasPhone ? 'tel:'.$phoneDigits : null,
            'text_url' => $hasPhone ? $customerUrl.'?compose=text#customer-communication' : null,
            'repair_order_url' => $appointment->repair_order_id !== null
                ? route('operations.repair-orders.show', $appointment->repairOrder)
                : null,
            'show_url' => route('operations.appointments.show', $appointment),
            'create_url' => null,
        ];

        if ($withDayLabel) {
            $row['day_label'] = $startsAt->format('D, M j');
        }

        return $row;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Appointment>  $appointments
     * @return list<Appointment>
     */
    public function sortForViewer(\Illuminate\Support\Collection $appointments, ?User $viewer): array
    {
        if ($viewer === null) {
            return $appointments->all();
        }

        return $appointments
            ->sortBy([
                fn (Appointment $appointment): int => (int) $appointment->advisor_user_id === (int) $viewer->id ? 0 : 1,
                fn (Appointment $appointment) => $appointment->starts_at?->timestamp ?? PHP_INT_MAX,
            ])
            ->values()
            ->all();
    }
}
