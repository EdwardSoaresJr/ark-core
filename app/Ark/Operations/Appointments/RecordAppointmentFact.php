<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;

final class RecordAppointmentFact
{
    public function __construct(private readonly OperationalEventRecorder $recorder) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        Appointment $appointment,
        OperationalEventName $eventName,
        ?User $actor = null,
        array $payload = [],
    ): void {
        $appointment->loadMissing('repairOrder');

        $body = [
            'appointment_id' => $appointment->id,
            'repair_order_id' => $appointment->repairOrder?->repair_order_id,
            'repair_order_status' => $appointment->repairOrder?->status?->value,
            'appointment_status' => $appointment->status->value,
            'starts_at' => $appointment->starts_at?->toIso8601String(),
            ...$payload,
        ];

        $this->recorder->record($eventName, $appointment, null, $actor, $body);

        if ($appointment->repairOrder !== null) {
            $this->recorder->record($eventName, $appointment->repairOrder, null, $actor, $body);
        }
    }
}
