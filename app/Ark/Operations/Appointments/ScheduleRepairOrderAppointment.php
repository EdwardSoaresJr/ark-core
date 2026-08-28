<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Book or move an RO onto the appointment calendar without touching repair workflow status.
 */
final class ScheduleRepairOrderAppointment
{
    public function __construct(private readonly RecordAppointmentFact $facts) {}

    /**
     * @param  array{starts_at: Carbon|string, ends_at?: Carbon|string|null, notes?: string|null, kind?: AppointmentKind|string|null, duration_minutes?: int|null}  $input
     */
    public function execute(RepairOrder $repairOrder, User $actor, array $input): Appointment
    {
        $startsAt = $this->instant($input['starts_at']);
        $endsAt = isset($input['ends_at']) && $input['ends_at'] !== null
            ? $this->instant($input['ends_at'])
            : $startsAt->copy()->addMinutes((int) ($input['duration_minutes'] ?? 60));

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Appointment must end after it starts.');
        }

        $kind = $this->kind($repairOrder, $input['kind'] ?? null);
        $notes = isset($input['notes']) ? trim((string) $input['notes']) : null;
        $notes = $notes === '' ? null : $notes;

        $upcoming = Appointment::query()
            ->where('repair_order_id', $repairOrder->id)
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
            ->orderBy('starts_at')
            ->first();

        $workflowBefore = $repairOrder->status;

        if ($upcoming !== null) {
            $previousStarts = $upcoming->starts_at?->toIso8601String();
            $upcoming->forceFill([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'notes' => $notes ?? $upcoming->notes,
                'kind' => $kind,
                'customer_id' => $repairOrder->customer_id,
                'vehicle_id' => $repairOrder->vehicle_id,
            ])->save();

            $this->facts->execute($upcoming, OperationalEventName::AppointmentRescheduled, $actor, [
                'previous_starts_at' => $previousStarts,
            ]);

            $this->assertWorkflowUntouched($repairOrder, $workflowBefore);

            return $upcoming->fresh();
        }

        $appointment = Appointment::query()->create([
            'customer_id' => $repairOrder->customer_id,
            'vehicle_id' => $repairOrder->vehicle_id,
            'repair_order_id' => $repairOrder->id,
            'created_by_user_id' => $actor->id,
            'advisor_user_id' => $actor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'concern' => (string) ($repairOrder->concern_summary ?: 'Scheduled visit'),
            'notes' => $notes,
            'status' => AppointmentStatus::Scheduled,
            'kind' => $kind,
        ]);

        $this->facts->execute($appointment, OperationalEventName::AppointmentScheduled, $actor);
        $this->assertWorkflowUntouched($repairOrder, $workflowBefore);

        return $appointment->fresh(['repairOrder']);
    }

    private function kind(RepairOrder $repairOrder, AppointmentKind|string|null $kind): AppointmentKind
    {
        if ($kind instanceof AppointmentKind) {
            return $kind;
        }

        if (is_string($kind) && $kind !== '') {
            return AppointmentKind::from($kind);
        }

        return $repairOrder->status->is(RepairOrderStatus::WaitingParts)
            ? AppointmentKind::Return
            : AppointmentKind::Intake;
    }

    private function instant(Carbon|string $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return ShopDisplayTimezone::parseLocal($value)->utc();
    }

    private function assertWorkflowUntouched(RepairOrder $repairOrder, mixed $before): void
    {
        $fresh = $repairOrder->fresh();
        $beforeValue = is_object($before) && isset($before->value) ? $before->value : (string) $before;
        $afterValue = $fresh?->status?->value;

        if ($afterValue !== $beforeValue) {
            throw new InvalidArgumentException('Scheduling must not change repair workflow status.');
        }
    }
}
