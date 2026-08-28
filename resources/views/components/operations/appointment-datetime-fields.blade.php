@props([
    'startsAt' => null,
    'endsAt' => null,
    'durationMinutes' => null,
    'slotMinutes' => null,
])

@php
    use App\Ark\Operations\Appointments\AppointmentSlotMinutes;
    use App\Ark\Operations\Appointments\SchedulingHours;
    use App\Ark\Operations\Settings\ShopDisplayTimezone;
    use App\Ark\Operations\Settings\ShopSettings;
    use Illuminate\Support\Carbon;

    $slotMinutes = $slotMinutes ?? AppointmentSlotMinutes::resolve();
    $timeOptions = AppointmentSlotMinutes::timeOptions($slotMinutes);
    $durationOptions = AppointmentSlotMinutes::durationOptions($slotMinutes);

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

    if ($start['date'] === null) {
        // Default to the next open slot — after close that means the next
        // business morning, not tonight snapped to the current time.
        $suggested = SchedulingHours::nextOpenSlot(
            ShopSettings::current()->schedulingHours(),
            ShopDisplayTimezone::now(),
            $slotMinutes,
        );
        $start['date'] = $suggested->format('Y-m-d');
        $start['time'] = AppointmentSlotMinutes::snapTimeString($suggested->format('H:i'), $slotMinutes);
    }

    $resolvedDuration = old('duration_minutes', $durationMinutes);
    if ($resolvedDuration === null && $start['carbon'] && $end['carbon']) {
        $resolvedDuration = max($slotMinutes, (int) $start['carbon']->diffInMinutes($end['carbon']));
    }
    if ($resolvedDuration === null) {
        $resolvedDuration = $slotMinutes;
    }
    $resolvedDuration = AppointmentSlotMinutes::snapDurationMinutes((int) $resolvedDuration, $slotMinutes);
@endphp

<div class="grid gap-3 sm:grid-cols-[1.2fr_1fr_1fr]" data-appointment-slot-minutes="{{ $slotMinutes }}">
    <label class="block">
        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Day</span>
        <input type="date" name="starts_date" value="{{ old('starts_date', $start['date']) }}" required class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
    </label>
    <label class="block">
        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Time</span>
        <select name="starts_time" required class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
            @foreach ($timeOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('starts_time', $start['time']) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Length</span>
        <select name="duration_minutes" required class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
            @foreach ($durationOptions as $value => $label)
                <option value="{{ $value }}" @selected((int) old('duration_minutes', $resolvedDuration) === (int) $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
</div>
