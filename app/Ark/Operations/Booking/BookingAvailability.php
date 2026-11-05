<?php

namespace App\Ark\Operations\Booking;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentRequestAvailability;
use App\Ark\Operations\Appointments\AppointmentRequestAvailabilityProjection;
use App\Ark\Operations\Appointments\AppointmentSlotMinutes;
use App\Ark\Operations\Appointments\AppointmentStaffOptions;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Appointments\ScheduleRequestWindows;
use App\Ark\Operations\Appointments\SchedulingCapacityCalculator;
use App\Ark\Operations\Booking\Contracts\BookingCandidate;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Settings\ShopSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What online booking may offer, from the scheduler's own rules:
 *
 * - days: Appointment Request Availability (weekly days, date exceptions, horizon, notice);
 * - intake: the shop's daily request limit (pending and scheduled requests for that day);
 * - capacity: SchedulingCapacityCalculator. A day already at its target is not offered;
 * - instant starts: scheduling hours, latest arrival, slot step, concurrent appointments, capacity;
 * - extension constraints (service area, and travel or routing rules a host adds). When a required
 *   one cannot run, no exact time is offered and instant booking falls back to a request.
 *
 * Submission calls the same refuse* methods inside the booking lock.
 */
final class BookingAvailability
{
    public const SAME_DAY_LEAD_MINUTES = 60;

    public const FULL = 'Fully booked';

    public const UNCHECKED_NOTICE = 'We can\'t confirm times online right now, so this will be a request. Pick a day and a time of day, and the shop will contact you to confirm.';

    public function __construct(
        private readonly AppointmentRequestAvailabilityProjection $requestDays,
        private readonly SchedulingCapacityCalculator $capacity,
        private readonly BookingConstraints $constraints,
        private readonly AppointmentStaffOptions $staff,
    ) {}

    /**
     * @return array{
     *     accepting: bool,
     *     mode: string,
     *     timezone: string,
     *     dates: list<array{date: string, label: string, available: bool, reason: string|null}>,
     *     periods: list<array{value: string, label: string}>,
     *     message: string|null,
     *     notice: string|null
     * }
     */
    public function forContext(BookingContext $context, ?CarbonInterface $now = null): array
    {
        $settings = BookingSettings::forShop();
        $mode = $context->mode($settings);
        $now = $this->now($now);
        $book = $this->requestDays->forBook(null, $now);

        $dates = $this->dates($book['dates'], $mode, $context, $settings, $now);
        if ($mode === BookingMode::Instant && $this->unchecked() !== []) {
            $mode = BookingMode::Request;
            $dates = $this->dates($book['dates'], $mode, $context, $settings, $now);
        }

        $open = array_values(array_filter($dates, fn (array $d): bool => $d['available']));

        return [
            'accepting' => $open !== [],
            'mode' => $mode->value,
            'timezone' => ShopDisplayTimezone::resolve(),
            'dates' => $dates,
            'periods' => $mode === BookingMode::Request ? AppointmentRequestAvailability::enabledPeriodOptions() : [],
            'message' => $open === []
                ? ($dates !== [] && $dates[0]['reason'] !== null && $dates[0]['reason'] !== self::FULL
                    ? $dates[0]['reason']
                    : 'We are not taking online bookings for the next few days. Call us and we will find a time.')
                : null,
            'notice' => $this->unchecked() !== [] ? self::UNCHECKED_NOTICE : null,
        ];
    }

    /**
     * Required checks that could not run while this instance evaluated availability. While any are
     * listed, no exact time is offered or confirmed; requests still go to staff.
     *
     * @return list<string>
     */
    public function unchecked(): array
    {
        return $this->constraints->unavailable();
    }

    /**
     * @param  list<array{date: string, label: string}>  $days
     * @return list<array{date: string, label: string, available: bool, reason: string|null}>
     */
    private function dates(array $days, BookingMode $mode, BookingContext $context, array $settings, CarbonInterface $now): array
    {
        $dates = [];
        foreach ($days as $row) {
            $reason = $mode === BookingMode::Instant
                ? ($this->slotsFor($row['date'], $context, $now) === [] ? ($this->refuseDay($row['date'], $context, $settings, alwaysCheckCapacity: true) ?? self::FULL) : null)
                : $this->refuseDay($row['date'], $context, $settings);

            $dates[] = [
                'date' => $row['date'],
                'label' => $row['label'],
                'available' => $reason === null,
                'reason' => $reason,
            ];
        }

        return $dates;
    }

    /**
     * Request windows still open on that day (a morning request is not offered at 3 PM today).
     *
     * @return list<array{value: string, label: string}>
     */
    public function periodsFor(string $date, ?CarbonInterface $now = null): array
    {
        $now = $this->now($now);
        $periods = AppointmentRequestAvailability::enabledPeriodOptions();

        if ($date !== $now->toDateString()) {
            return $periods;
        }

        return array_values(array_filter($periods, function (array $period) use ($now): bool {
            $window = ScheduleRequestWindows::windowForPeriod($period['value']);

            return $window === null || $now->format('H:i') < $window['close'];
        }));
    }

    /**
     * Exact start times for instant booking.
     *
     * @return list<array{value: string, label: string}>
     */
    public function slotsFor(string $date, BookingContext $context, ?CarbonInterface $now = null): array
    {
        $now = $this->now($now);
        $settings = BookingSettings::forShop();

        if (! $this->isRequestableDay($date, $now)
            || $this->refuseDay($date, $context, $settings, alwaysCheckCapacity: true) !== null
            || $this->unchecked() !== []) {
            return [];
        }

        $timezone = ShopDisplayTimezone::resolve();
        $day = Carbon::parse($date, $timezone)->startOfDay();
        $row = ShopSettings::current()->schedulingHours()[strtolower($day->englishDayOfWeek)] ?? null;
        if ($row === null || ! $row['enabled']) {
            return [];
        }

        $step = AppointmentSlotMinutes::resolve();
        $duration = $context->durationMinutes();
        $latest = ScheduleRequestWindows::forShop()['latest_appointment_arrival'];
        $earliest = $now->copy()->addMinutes(self::SAME_DAY_LEAD_MINUTES);
        $existing = $this->activeAppointmentsOn($day);
        $limit = $this->concurrentLimit($settings);

        $slots = [];
        $start = $day->copy()->setTimeFromTimeString($row['open']);
        $close = $day->copy()->setTimeFromTimeString($row['close']);

        while ($start->copy()->addMinutes($duration)->lessThanOrEqualTo($close)) {
            $end = $start->copy()->addMinutes($duration);
            $clock = $start->format('H:i');

            if ($start->greaterThanOrEqualTo($earliest)
                && ($latest === null || $clock <= $latest)
                && $this->overlapCount($existing, $start, $end) < $limit
                && $this->constraints->confirms(new BookingCandidate($date, $context, null, $start, $end))) {
                $slots[] = ['value' => $clock, 'label' => $start->format('g:i A')];
            }

            $start->addMinutes($step);
        }

        return $slots;
    }

    /**
     * Authoritative check before saving a request. Null when it may be taken.
     */
    public function refuseRequest(BookingContext $context, string $date, string $period, ?CarbonInterface $now = null): ?string
    {
        $now = $this->now($now);

        if (! $this->isRequestableDay($date, $now)) {
            return 'That day is no longer available. Pick another day.';
        }

        if (! in_array($period, array_column($this->periodsFor($date, $now), 'value'), true)) {
            return 'That time of day is no longer available. Pick another.';
        }

        return $this->refuseDay($date, $context, BookingSettings::forShop());
    }

    /**
     * Authoritative check before saving an instant appointment. Null when the start is still open.
     */
    public function refuseSlot(BookingContext $context, string $date, string $time, ?CarbonInterface $now = null): ?string
    {
        foreach ($this->slotsFor($date, $context, $now) as $slot) {
            if ($slot['value'] === $time) {
                return null;
            }
        }

        $now = $this->now($now);
        if (! $this->isRequestableDay($date, $now)) {
            return 'That day is no longer available. Pick another day.';
        }

        $reason = $this->refuseDay($date, $context, BookingSettings::forShop(), alwaysCheckCapacity: true);

        return $reason !== null && $reason !== self::FULL ? $reason : 'That time was just taken. Pick another time.';
    }

    private function refuseDay(string $date, BookingContext $context, array $settings, bool $alwaysCheckCapacity = false): ?string
    {
        $limit = $settings['limits']['daily_requests'];
        if ($limit !== null && $this->requestsOn($date) >= $limit) {
            return self::FULL;
        }

        if ($alwaysCheckCapacity || $settings['limits']['respect_capacity']) {
            $day = Carbon::parse($date, ShopDisplayTimezone::resolve())->startOfDay();
            $snapshot = $this->capacity->forDayWithProposedWorkload($day, $context->workloadHours());
            if ($snapshot->available && $snapshot->isBeyondTarget()) {
                return self::FULL;
            }
        }

        return $this->constraints->refuse(new BookingCandidate($date, $context));
    }

    private function isRequestableDay(string $date, CarbonInterface $now): bool
    {
        return in_array($date, array_column($this->requestDays->forBook(null, $now)['dates'], 'date'), true);
    }

    private function requestsOn(string $date): int
    {
        return AppointmentRequest::query()
            ->whereDate('preferred_date', $date)
            ->whereIn('status', [AppointmentRequestStatus::Pending->value, AppointmentRequestStatus::Scheduled->value])
            ->count();
    }

    /**
     * Appointments that may overlap one start. Defaults to one per schedulable bay, at least one.
     */
    private function concurrentLimit(array $settings): int
    {
        $configured = $settings['limits']['concurrent_appointments'];

        return $configured ?? max(1, $this->staff->schedulableWorkstations()->count());
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function activeAppointmentsOn(CarbonInterface $day): Collection
    {
        return Appointment::query()
            ->whereIn('status', array_map(fn (AppointmentStatus $s): string => $s->value, AppointmentStatus::activeToday()))
            ->where('starts_at', '<', $day->copy()->endOfDay()->utc())
            ->where('ends_at', '>', $day->copy()->startOfDay()->utc())
            ->get(['id', 'starts_at', 'ends_at']);
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     */
    private function overlapCount(Collection $appointments, CarbonInterface $start, CarbonInterface $end): int
    {
        return $appointments->filter(
            fn (Appointment $a): bool => $a->starts_at->lessThan($end) && $a->ends_at->greaterThan($start),
        )->count();
    }

    private function now(?CarbonInterface $now): CarbonInterface
    {
        return ($now ?? ShopDisplayTimezone::now())->copy()->timezone(ShopDisplayTimezone::resolve());
    }
}
