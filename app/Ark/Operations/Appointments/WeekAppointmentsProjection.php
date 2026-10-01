<?php

namespace App\Ark\Operations\Appointments;

use App\Models\User;
use Illuminate\Support\Carbon;

final class WeekAppointmentsProjection
{
    public function __construct(
        private readonly AppointmentScheduleRowPresenter $rows,
    ) {}

    /**
     * @return array{
     *     week_start: string,
     *     week_label: string,
     *     days: list<array{date: string, day_label: string, rows: list<array<string, mixed>>}>,
     *     total_count: int,
     * }
     */
    public function resolve(?Carbon $weekStart = null, ?User $viewer = null): array
    {
        $dayStart = ($weekStart ?? now())->copy()->startOfDay();
        $dayEnd = $dayStart->copy()->addDays(6)->endOfDay();

        $appointments = Appointment::query()
            ->with(['customer', 'vehicle', 'advisor', 'technician', 'workstation', 'repairOrder'])
            ->where('starts_at', '<=', $dayEnd)
            ->where('ends_at', '>=', $dayStart)
            ->whereNot('status', AppointmentStatus::Canceled)
            ->orderBy('starts_at')
            ->get();

        $days = [];

        foreach ($appointments->groupBy(
            fn (Appointment $appointment): string => $appointment->starts_at->toDateString(),
        ) as $date => $dayAppointments) {
            $dayLabel = Carbon::parse($date)->format('l, M j');
            $sorted = $this->rows->sortForViewer($dayAppointments, $viewer);

            $days[] = [
                'date' => $date,
                'day_label' => $dayLabel,
                'rows' => array_map(
                    fn (Appointment $appointment): array => $this->rows->present($appointment, $viewer),
                    $sorted,
                ),
            ];
        }

        return [
            'week_start' => $dayStart->toDateString(),
            'week_label' => 'Week of '.$dayStart->format('M j, Y'),
            'days' => $days,
            'total_count' => $appointments->count(),
        ];
    }
}
