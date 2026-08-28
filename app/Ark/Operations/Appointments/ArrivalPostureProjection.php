<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\OperationsFeatures;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Disposable read model: Arrival Posture on a Repair Order.
 *
 * Owns no persistence. Rebuild entirely from Appointment authority at any time.
 * Do not cache posture on repair_orders.
 *
 * Association is strict: appointments.repair_order_id = current RO id only.
 */
final class ArrivalPostureProjection
{
    public function forRepairOrder(RepairOrder $repairOrder): ArrivalPosture
    {
        $scheduleUrl = OperationsFeatures::appointmentsEnabled()
            ? ScheduleUrl::to(['repair_order' => $repairOrder->id])
            : null;

        $appointment = $this->selectLinkedAppointment($repairOrder);

        if ($appointment === null) {
            return ArrivalPosture::absent($scheduleUrl);
        }

        $source = $appointment->status;
        $posture = $this->floorPosture($source);
        $headline = $this->headline($posture);
        [$whenLabel, $subtitle] = $this->labelsFor($appointment, $posture);

        return new ArrivalPosture(
            present: true,
            posture: $posture,
            headline: $headline,
            whenLabel: $whenLabel,
            subtitle: $subtitle,
            appointmentUrl: route('operations.appointments.show', $appointment),
            scheduleUrl: $scheduleUrl,
            sourceStatus: $source,
            appointmentId: $appointment->id,
        );
    }

    private function selectLinkedAppointment(RepairOrder $repairOrder): ?Appointment
    {
        /** @var Collection<int, Appointment> $linked */
        $linked = Appointment::query()
            ->where('repair_order_id', $repairOrder->id)
            ->orderBy('starts_at')
            ->get();

        if ($linked->isEmpty()) {
            return null;
        }

        $active = $linked
            ->filter(static fn (Appointment $appointment): bool => $appointment->status->isUpcoming())
            ->sortBy(static fn (Appointment $appointment) => $appointment->starts_at?->getTimestamp() ?? PHP_INT_MAX)
            ->values();

        if ($active->isNotEmpty()) {
            return $active->first();
        }

        $arrived = $linked->first(static fn (Appointment $appointment): bool => $appointment->status === AppointmentStatus::Arrived);
        if ($arrived !== null) {
            return $arrived;
        }

        return $linked
            ->sortByDesc(static fn (Appointment $appointment) => $appointment->starts_at?->getTimestamp() ?? 0)
            ->first();
    }

    /**
     * @return 'scheduled'|'arrived'|'completed'|'no_show'|'canceled'
     */
    private function floorPosture(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::Scheduled, AppointmentStatus::Confirmed => 'scheduled',
            AppointmentStatus::Arrived => 'arrived',
            AppointmentStatus::Completed => 'completed',
            AppointmentStatus::NoShow => 'no_show',
            AppointmentStatus::Canceled => 'canceled',
        };
    }

    private function headline(string $posture): string
    {
        return match ($posture) {
            'scheduled' => 'Scheduled',
            'arrived' => 'Arrived',
            'completed' => 'Completed',
            'no_show' => 'No show',
            'canceled' => 'Canceled',
        };
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function labelsFor(Appointment $appointment, string $posture): array
    {
        if ($posture === 'arrived') {
            if ($appointment->arrived_at !== null) {
                return [$this->formatWhen($appointment->arrived_at), null];
            }

            // Historical Arrived without evidence — do not imply starts_at was arrival time.
            $scheduled = $appointment->starts_at !== null
                ? 'Scheduled '.$this->formatWhen($appointment->starts_at)
                : null;

            return [$scheduled ?? 'Arrival time not recorded', null];
        }

        $instant = match ($posture) {
            'scheduled' => $appointment->starts_at,
            'completed' => $appointment->starts_at, // no completion timestamp on Appointment yet
            'no_show' => $appointment->starts_at,
            'canceled' => $appointment->canceled_at ?? $appointment->starts_at,
            default => $appointment->starts_at,
        };

        $when = $instant !== null ? $this->formatWhen($instant) : null;
        $subtitle = $posture === 'scheduled' ? 'Vehicle has not arrived.' : null;

        return [$when ?? '—', $subtitle];
    }

    private function formatWhen(CarbonInterface $instant): string
    {
        $local = ShopDisplayTimezone::present($instant);
        $today = ShopDisplayTimezone::now()->startOfDay();
        $day = $local->copy()->startOfDay();
        $time = $local->format('g:i A');

        if ($day->equalTo($today)) {
            return 'Today · '.$time;
        }

        if ($day->equalTo($today->copy()->addDay())) {
            return 'Tomorrow · '.$time;
        }

        if ($day->equalTo($today->copy()->subDay())) {
            return 'Yesterday · '.$time;
        }

        return $local->format('M j').' · '.$time;
    }
}
