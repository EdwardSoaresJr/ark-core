<?php

namespace App\Ark\Operations\Appointments;

use App\Models\User;
use Illuminate\Support\Carbon;

final class UpcomingScheduleProjection
{
    private const WINDOW_DAYS = 7;

    public function __construct(
        private readonly AppointmentScheduleRowPresenter $rows,
    ) {}

    /**
     * @return array{
     *     today: list<array<string, mixed>>,
     *     tomorrow: list<array<string, mixed>>,
     *     upcoming: list<array<string, mixed>>,
     *     total_count: int,
     * }
     */
    public function resolve(?Carbon $from = null, ?User $viewer = null): array
    {
        $from ??= now();
        $windowStart = $from->copy()->startOfDay();
        $windowEnd = $from->copy()->addDays(self::WINDOW_DAYS - 1)->endOfDay();
        $tomorrowStart = $from->copy()->addDay()->startOfDay();
        $dayAfterTomorrow = $from->copy()->addDays(2)->startOfDay();

        $appointments = Appointment::query()
            ->with(['customer', 'vehicle', 'advisor', 'technician', 'repairOrder'])
            ->whereIn('status', array_map(
                static fn (AppointmentStatus $status): string => $status->value,
                AppointmentStatus::activeToday(),
            ))
            ->where('starts_at', '<=', $windowEnd)
            ->where('ends_at', '>=', $windowStart)
            ->orderBy('starts_at')
            ->get();

        $sorted = $this->rows->sortForViewer($appointments, $viewer);

        $today = [];
        $tomorrow = [];
        $upcoming = [];

        foreach ($sorted as $appointment) {
            $row = $this->rows->present($appointment, $viewer, withDayLabel: true);
            $startsAt = $appointment->starts_at->copy()->startOfDay();

            if ($startsAt->equalTo($windowStart)) {
                $today[] = $row;

                continue;
            }

            if ($startsAt->equalTo($tomorrowStart)) {
                $tomorrow[] = $row;

                continue;
            }

            if ($startsAt->greaterThanOrEqualTo($dayAfterTomorrow)) {
                $upcoming[] = $row;
            }
        }

        return [
            'today' => $today,
            'tomorrow' => $tomorrow,
            'upcoming' => $upcoming,
            'total_count' => count($today) + count($tomorrow) + count($upcoming),
        ];
    }
}
