<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ScheduleRepairOrderAppointmentController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        ScheduleRepairOrderAppointment $schedule,
    ): RedirectResponse {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'kind' => ['nullable', Rule::enum(AppointmentKind::class)],
        ]);

        $startsAt = ShopDisplayTimezone::parseLocal($data['starts_at']);

        $schedule->execute($repairOrder, $request->user(), [
            'starts_at' => $startsAt,
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'notes' => $data['notes'] ?? null,
            'kind' => $data['kind'] ?? null,
        ]);

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Appointment saved. Repair status was not changed.');
    }
}
