@props([
    'startsAt' => null,
    'endsAt' => null,
    'durationMinutes' => null,
    'slotMinutes' => null,
    'preferredPeriod' => null,
    'preferredDate' => null,
    'requestPreferenceDetail' => null,
    'estimatedLaborHours' => null,
])

@php
    use App\Ark\Operations\Appointments\AppointmentSlotMinutes;
    use App\Ark\Operations\Appointments\ScheduleRequestWindows;
    use App\Ark\Operations\Appointments\SchedulingHours;
    use App\Ark\Operations\Settings\ShopDisplayTimezone;
    use App\Ark\Operations\Settings\ShopSettings;
    use Illuminate\Support\Carbon;

    $slotMinutes = $slotMinutes ?? AppointmentSlotMinutes::resolve();
    $durationOptions = AppointmentSlotMinutes::durationOptions($slotMinutes);
    $preferredPeriod = old('preferred_period', $preferredPeriod);
    $preferredPeriod = is_string($preferredPeriod) && $preferredPeriod !== '' ? $preferredPeriod : null;
    $preferredDate = old('preferred_date', $preferredDate);
    $preferredDate = is_string($preferredDate) && $preferredDate !== '' ? $preferredDate : null;

    $parse = function (?string $value) use ($slotMinutes): array {
        if (! filled($value)) {
            return ['date' => null, 'time' => null, 'carbon' => null];
        }

        try {
            $carbon = str_contains($value, 'T') || preg_match('/^\d{4}-\d{2}-\d{2}/', $value)
                ? ShopDisplayTimezone::parseLocal($value)
                : Carbon::parse($value, ShopDisplayTimezone::resolve());
        } catch (\Throwable) {
            return ['date' => null, 'time' => null, 'carbon' => null];
        }

        return [
            'date' => $carbon->format('Y-m-d'),
            'time' => AppointmentSlotMinutes::snapTimeString($carbon->format('H:i'), $slotMinutes),
            'carbon' => $carbon,
        ];
    };

    $start = $parse(old('starts_at', $startsAt));
    $end = $parse(old('ends_at', $endsAt));

    if ($start['date'] === null && $preferredDate !== null) {
        $start['date'] = $preferredDate;
    }

    if ($start['date'] === null) {
        $suggested = SchedulingHours::nextOpenSlot(
            ShopSettings::current()->schedulingHours(),
            ShopDisplayTimezone::now(),
            $slotMinutes,
        );
        $start['date'] = $suggested->format('Y-m-d');
        // Do not preselect a clock time from the daypart window open.
        $start['time'] = null;
    }

    $timeOptionsPreferred = ScheduleRequestWindows::timeOptionsForPeriod(
        $preferredPeriod,
        $start['date'],
        null,
        $slotMinutes,
    );
    $timeOptionsAll = AppointmentSlotMinutes::timeOptions($slotMinutes);
    $selectedTime = old('starts_time', $start['time']);
    // Never invent the first slot as selected when converting a preference.
    if ($preferredPeriod !== null && ($selectedTime === null || $selectedTime === '')) {
        $selectedTime = '';
    } elseif ($selectedTime === null || $selectedTime === '') {
        $selectedTime = array_key_first($timeOptionsAll);
    }

    $outsidePreference = is_string($selectedTime)
        && $selectedTime !== ''
        && $preferredPeriod !== null
        && ScheduleRequestWindows::isTimeOutsidePreferredPeriod($selectedTime, $preferredPeriod);

    $resolvedDuration = old('duration_minutes', $durationMinutes);
    if ($resolvedDuration === null && $start['carbon'] && $end['carbon']) {
        $resolvedDuration = max($slotMinutes, (int) $start['carbon']->diffInMinutes($end['carbon']));
    }
    if ($resolvedDuration === null) {
        $resolvedDuration = $slotMinutes;
    }
    $resolvedDuration = AppointmentSlotMinutes::snapDurationMinutes((int) $resolvedDuration, $slotMinutes);

    $requestPreferenceDetail = $requestPreferenceDetail
        ?? ($preferredPeriod
            ? \App\Ark\Operations\Appointments\AppointmentExpectationFormatter::requestedDetail($start['date'] ?? $preferredDate, $preferredPeriod)
            : null);
@endphp

<div class="d-flex flex-column gap-3" data-appointment-slot-minutes="{{ $slotMinutes }}">
    @if ($preferredPeriod)
        <input type="hidden" name="preferred_period" value="{{ $preferredPeriod }}">
        @if ($preferredDate)
            <input type="hidden" name="preferred_date" value="{{ $preferredDate }}">
        @endif
        <div class="alert alert-info mb-0" data-appointment-requested>
            <div class="subheader mb-1">Requested</div>
            <div class="fw-medium">{{ $requestPreferenceDetail }}</div>
            <div class="small text-secondary mt-1">Choose an exact appointment time. Preference guides the options - it is not the confirmed appointment.</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-sm-4">
            <label class="form-label" for="appointment-starts-date">Day</label>
            <input type="date" id="appointment-starts-date" name="starts_date" value="{{ old('starts_date', $start['date']) }}" required class="form-control">
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="appointment-starts-time">Time</label>
            <select id="appointment-starts-time" name="starts_time" required @class(['form-select', 'is-invalid' => $errors->has('starts_time')])>
                @if ($preferredPeriod)
                    <option value="" @selected($selectedTime === '' || $selectedTime === null)>Select a time…</option>
                    @if ($timeOptionsPreferred !== [])
                        <optgroup label="Within requested preference">
                            @foreach ($timeOptionsPreferred as $value => $label)
                                <option value="{{ $value }}" @selected($selectedTime === $value)>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    <optgroup label="Other times (override)">
                        @foreach ($timeOptionsAll as $value => $label)
                            @continue(isset($timeOptionsPreferred[$value]))
                            <option value="{{ $value }}" @selected($selectedTime === $value)>{{ $label }}</option>
                        @endforeach
                    </optgroup>
                @else
                    @foreach ($timeOptionsAll as $value => $label)
                        <option value="{{ $value }}" @selected($selectedTime === $value)>{{ $label }}</option>
                    @endforeach
                @endif
            </select>
            @if ($outsidePreference && $requestPreferenceDetail)
                <div class="small fw-medium text-warning mt-1">
                    Outside customer request - {{ $requestPreferenceDetail }}.
                </div>
            @endif
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="appointment-duration">Appointment length</label>
            <select id="appointment-duration" name="duration_minutes" required class="form-select">
                @foreach ($durationOptions as $value => $label)
                    <option value="{{ $value }}" @selected((int) old('duration_minutes', $resolvedDuration) === (int) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-operations.appointment-reserved-labor
        :value="$estimatedLaborHours"
        :duration-minutes="$resolvedDuration"
    />
</div>
