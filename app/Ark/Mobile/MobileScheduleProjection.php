<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentScheduleRowPresenter;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\OperationsFeatures;
use App\Models\User;
use Illuminate\Support\Carbon;

final class MobileScheduleProjection
{
    public function __construct(
        private readonly AppointmentScheduleRowPresenter $rows,
        private readonly MobileAppointmentRowProjection $mobileRows,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forDay(?Carbon $day, User $viewer): array
    {
        $day ??= now();

        if (! OperationsFeatures::appointmentsEnabled()) {
            return [
                'enabled' => false,
                'date' => $day->toDateString(),
                'date_label' => 'Schedule unavailable',
                'rows' => [],
                'count' => 0,
                'poll_after_seconds' => 60,
            ];
        }

        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        $appointments = Appointment::query()
            ->with(['customer', 'vehicle', 'advisor', 'technician', 'repairOrder'])
            ->whereIn('status', array_map(
                static fn (AppointmentStatus $status): string => $status->value,
                AppointmentStatus::activeToday(),
            ))
            ->where('starts_at', '<=', $dayEnd)
            ->where('ends_at', '>=', $dayStart)
            ->orderBy('starts_at')
            ->get();

        $sorted = $this->rows->sortForViewer($appointments, $viewer);
        $mobileRows = array_map(
            fn (Appointment $appointment): array => $this->mobileRows->present($appointment, $viewer),
            $sorted,
        );

        return [
            'enabled' => true,
            'date' => $dayStart->toDateString(),
            'date_label' => $dayStart->isToday() ? 'Today' : $dayStart->format('l, M j'),
            'rows' => $mobileRows,
            'count' => count($mobileRows),
            'poll_after_seconds' => 60,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function upcomingForCustomer(int $customerId, User $viewer): ?array
    {
        if (! OperationsFeatures::appointmentsEnabled()) {
            return null;
        }

        $appointment = Appointment::query()
            ->with(['customer', 'vehicle', 'advisor', 'repairOrder'])
            ->where('customer_id', $customerId)
            ->whereIn('status', array_map(
                static fn (AppointmentStatus $status): string => $status->value,
                AppointmentStatus::activeToday(),
            ))
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->first();

        if ($appointment === null) {
            return null;
        }

        return $this->mobileRows->present($appointment, $viewer);
    }
}
