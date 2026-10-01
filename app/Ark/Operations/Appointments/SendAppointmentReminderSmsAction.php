<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Models\User;
use Illuminate\Support\Carbon;

final class SendAppointmentReminderSmsAction
{
    public const TYPE_DAY_BEFORE = 'day_before';

    public const TYPE_HOURS_BEFORE = 'hours_before';

    public function __construct(
        private readonly AppointmentSmsDelivery $delivery,
    ) {}

    public function execute(Appointment $appointment, string $type, User $actor): void
    {
        $this->delivery->sendReminder($appointment, $type, $actor);
    }

    public function isDayBeforeDue(Appointment $appointment, ?Carbon $now = null): bool
    {
        if (! $appointment->reminder_day_before || $appointment->reminder_day_before_sent_at !== null) {
            return false;
        }

        $now ??= ShopDisplayTimezone::now();
        $starts = ShopDisplayTimezone::present($appointment->starts_at);
        $windowStart = $starts->copy()->subDay();
        $windowEnd = $windowStart->copy()->addMinutes(90);

        return $now->gte($windowStart) && $now->lt($windowEnd) && $now->lt($starts);
    }

    public function isHoursBeforeDue(Appointment $appointment, ?Carbon $now = null): bool
    {
        $hours = $appointment->reminder_hours_before;

        if ($hours === null || $hours < 1 || $appointment->reminder_hours_before_sent_at !== null) {
            return false;
        }

        $now ??= ShopDisplayTimezone::now();
        $starts = ShopDisplayTimezone::present($appointment->starts_at);
        $windowStart = $starts->copy()->subHours((int) $hours);
        $windowEnd = $windowStart->copy()->addMinutes(90);

        return $now->gte($windowStart) && $now->lt($windowEnd) && $now->lt($starts);
    }
}
