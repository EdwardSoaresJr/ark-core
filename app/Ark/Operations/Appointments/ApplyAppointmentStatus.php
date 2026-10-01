<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Events\OperationalEventName;

/**
 * Applies appointment status changes with arrival / no-show evidence rules.
 *
 * arrived_at and no_show_at are evidence, not current state.
 * Never mutates RepairOrder workflow status.
 */
final class ApplyAppointmentStatus
{
    public function __construct(private readonly RecordAppointmentFact $facts) {}

    public function execute(Appointment $appointment, AppointmentStatus $status, ?\App\Models\User $actor = null): void
    {
        $from = $appointment->status;
        $attributes = [
            'status' => $status,
            'canceled_at' => $status === AppointmentStatus::Canceled ? ($appointment->canceled_at ?? now()) : $appointment->canceled_at,
        ];

        if ($status === AppointmentStatus::Arrived && $appointment->arrived_at === null) {
            $attributes['arrived_at'] = now();
        }

        if ($status === AppointmentStatus::NoShow && $appointment->no_show_at === null) {
            $attributes['no_show_at'] = now();
        }

        $appointment->forceFill($attributes)->save();

        $this->facts->execute($appointment, $this->eventName($status), $actor, [
            'from_appointment_status' => $from->value,
        ]);
    }

    private function eventName(AppointmentStatus $status): OperationalEventName
    {
        return match ($status) {
            AppointmentStatus::Scheduled => OperationalEventName::AppointmentScheduled,
            AppointmentStatus::Confirmed => OperationalEventName::AppointmentConfirmed,
            AppointmentStatus::Arrived => OperationalEventName::AppointmentArrived,
            AppointmentStatus::Canceled => OperationalEventName::AppointmentCanceled,
            AppointmentStatus::NoShow => OperationalEventName::AppointmentNoShow,
            AppointmentStatus::Completed => OperationalEventName::AppointmentCompleted,
        };
    }
}
