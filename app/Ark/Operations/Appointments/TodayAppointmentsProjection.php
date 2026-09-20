<?php

namespace App\Ark\Operations\Appointments;

use App\Models\User;
use Illuminate\Support\Carbon;

final class TodayAppointmentsProjection
{
    public function __construct(
        private readonly AppointmentScheduleRowPresenter $rows,
    ) {}

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     total_count: int,
     *     date_label: string,
     * }
     */
    public function resolve(?Carbon $day = null, ?User $viewer = null): array
    {
        $day ??= now();
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

        $rows = array_map(
            fn (Appointment $appointment): array => $this->rows->present($appointment, $viewer),
            $sorted,
        );

        return [
            'rows' => $rows,
            'total_count' => count($rows),
            'date_label' => $dayStart->isToday() ? 'Today' : $dayStart->format('l, M j'),
        ];
    }
}
