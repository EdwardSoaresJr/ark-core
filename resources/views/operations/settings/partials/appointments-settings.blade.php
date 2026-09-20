<form method="POST" action="{{ route('operations.settings.shop.appointments.update') }}" class="mt-4 max-w-2xl space-y-4">
    @csrf
    @method('PATCH')

    <div class="rounded-md border border-slate-200 bg-white p-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-black text-slate-950">Enable Appointments</p>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Turn scheduling on when staff are trained and appointments have a clear path into Check In or repair orders.
                    Disabled by default — Work stays the advisor front door until you are ready.
                </p>
            </div>
            <label class="flex shrink-0 items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="appointments_enabled" value="0">
                <input
                    type="checkbox"
                    name="appointments_enabled"
                    value="1"
                    class="rounded border-slate-300"
                    @checked(old('appointments_enabled', $settings->appointments_enabled))
                >
                <span>{{ $settings->appointments_enabled ? 'Enabled' : 'Disabled' }}</span>
            </label>
        </div>
    </div>

    <div class="rounded-md border border-slate-200 bg-white p-4">
        <p class="text-sm font-black text-slate-950">Appointment time steps</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Schedule grid and book/edit time pickers use this increment — no scrolling minute-by-minute.
        </p>
        <label class="mt-3 block max-w-xs">
            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Increment</span>
            <select name="appointment_slot_minutes" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                @foreach (\App\Ark\Operations\Appointments\AppointmentSlotMinutes::settingOptions() as $minutes => $label)
                    <option value="{{ $minutes }}" @selected((int) old('appointment_slot_minutes', $settings->appointment_slot_minutes ?? 30) === $minutes)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="rounded-md border border-slate-200 bg-white p-4 space-y-4">
        <div>
            <p class="text-sm font-black text-slate-950">Scheduling capacity</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Appointments reserve soft shop capacity. Bay and technician assignments are optional planning — not required to book.
            </p>
        </div>

        <fieldset class="space-y-2">
            <legend class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Capacity basis</legend>
            @foreach (\App\Ark\Operations\Appointments\AppointmentCapacityBasis::cases() as $basis)
                <label class="flex items-center gap-2 text-sm text-slate-800">
                    <input
                        type="radio"
                        name="appointment_capacity_basis"
                        value="{{ $basis->value }}"
                        class="border-slate-300"
                        @checked(old('appointment_capacity_basis', $settings->appointment_capacity_basis ?? 'limiting_resource') === $basis->value)
                    >
                    <span>{{ $basis->label() }}</span>
                </label>
            @endforeach
        </fieldset>

        <label class="block max-w-xs">
            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Scheduling target</span>
            <div class="mt-0.5 flex items-center gap-2">
                <input
                    type="number"
                    name="appointment_scheduling_target_percent"
                    min="25"
                    max="300"
                    value="{{ old('appointment_scheduling_target_percent', $settings->appointment_scheduling_target_percent ?? 100) }}"
                    class="h-9 w-24 rounded-sm border border-slate-300 bg-white px-2 text-sm @error('appointment_scheduling_target_percent') border-rose-400 @enderror"
                >
                <span class="text-sm font-semibold text-slate-600">%</span>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">100% matches base capacity. Above 100% intentionally overpacks the day.</p>
            @error('appointment_scheduling_target_percent')
                <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </label>

        <fieldset class="space-y-2">
            <legend class="text-[10px] font-bold uppercase tracking-wide text-slate-400">When target is exceeded</legend>
            @foreach (\App\Ark\Operations\Appointments\AppointmentCapacityEnforcement::cases() as $enforcement)
                <label class="flex items-center gap-2 text-sm text-slate-800">
                    <input
                        type="radio"
                        name="appointment_capacity_enforcement"
                        value="{{ $enforcement->value }}"
                        class="border-slate-300"
                        @checked(old('appointment_capacity_enforcement', $settings->appointment_capacity_enforcement ?? 'warn') === $enforcement->value)
                    >
                    <span>{{ $enforcement->label() }}</span>
                </label>
            @endforeach
        </fieldset>
    </div>

    @php
        $weekdayLabels = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];
        $followShopHours = old(
            'scheduling_hours_follow_shop',
            ! $settings->usesCustomSchedulingHours(),
        );
        $followShopHours = filter_var($followShopHours, FILTER_VALIDATE_BOOL);
        $businessHoursWeekly = \App\Ark\Operations\Telephony\TelephonyCallFlowSettings::fromShopSettings($settings)->weeklyHours();
        $customSchedulingHours = old(
            'scheduling_hours',
            $settings->usesCustomSchedulingHours()
                ? $settings->schedulingHours()
                : $businessHoursWeekly,
        );
        $businessHoursSummary = \App\Ark\Operations\Settings\ShopCustomerHoursPresentation::summary($businessHoursWeekly)
            ?? 'No open days configured yet.';
    @endphp

    <div
        class="rounded-md border border-slate-200 bg-white p-4 space-y-4"
        x-data="{ followShop: {{ $followShopHours ? 'true' : 'false' }} }"
    >
        <div>
            <p class="text-sm font-black text-slate-950">Scheduling hours</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Staff appointment booking follows shop Business Hours by default.
                Customize only when scheduling should blacklist a day or use narrower windows than the phones.
            </p>
        </div>

        <label class="flex items-start gap-2 text-sm text-slate-800">
            <input type="hidden" name="scheduling_hours_follow_shop" value="0">
            <input
                type="checkbox"
                name="scheduling_hours_follow_shop"
                value="1"
                class="mt-0.5 rounded border-slate-300"
                x-model="followShop"
            >
            <span>
                <span class="font-semibold">Follow Business Hours</span>
                <span class="mt-0.5 block text-xs leading-5 text-slate-500">
                    {{ $businessHoursSummary }}
                    ·
                    <a
                        href="{{ route('operations.settings.shop.edit', ['section' => 'general']) }}"
                        class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950"
                    >Edit Business Hours</a>
                </span>
            </span>
        </label>

        <div x-show="!followShop" x-cloak class="space-y-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Custom scheduling windows</p>
            <p class="text-xs leading-5 text-slate-500">Uncheck a day to blacklist it for appointments. Open/close may be narrower than Business Hours.</p>
            @foreach ($weekdayLabels as $dayKey => $dayLabel)
                @php
                    $dayHours = $customSchedulingHours[$dayKey] ?? ['enabled' => false, 'open' => '08:00', 'close' => '17:00'];
                @endphp
                <div class="grid gap-2 rounded-sm border border-slate-200 bg-slate-50/60 px-2 py-2 sm:grid-cols-[7rem_1fr_1fr] sm:items-center">
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                        <input type="hidden" name="scheduling_hours[{{ $dayKey }}][enabled]" value="0">
                        <input
                            type="checkbox"
                            name="scheduling_hours[{{ $dayKey }}][enabled]"
                            value="1"
                            class="rounded border-slate-300"
                            @checked(old("scheduling_hours.{$dayKey}.enabled", $dayHours['enabled'] ?? false))
                        >
                        {{ $dayLabel }}
                    </label>
                    <input
                        type="time"
                        name="scheduling_hours[{{ $dayKey }}][open]"
                        value="{{ old("scheduling_hours.{$dayKey}.open", $dayHours['open'] ?? '08:00') }}"
                        class="h-9 w-full rounded-sm border-slate-300 bg-white text-sm text-slate-800"
                    >
                    <input
                        type="time"
                        name="scheduling_hours[{{ $dayKey }}][close]"
                        value="{{ old("scheduling_hours.{$dayKey}.close", $dayHours['close'] ?? '17:00') }}"
                        class="h-9 w-full rounded-sm border-slate-300 bg-white text-sm text-slate-800"
                    >
                </div>
            @endforeach
            @error('scheduling_hours')
                <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @php
        $requestAvailability = \App\Ark\Operations\Appointments\AppointmentRequestAvailability::forShop($settings);
    @endphp

    <div class="rounded-md border border-slate-200 bg-white p-4 space-y-4">
        <div>
            <p class="text-sm font-black text-slate-950">Appointment Request Availability</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Controls which days appointment requests may offer. Public booking presentation belongs to ARK Website.
                Independent of Business Hours and staff Scheduling hours above.
            </p>
        </div>

        <fieldset class="space-y-2">
            <legend class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Accept requests</legend>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($weekdayLabels as $dayKey => $dayLabel)
                    <label class="flex items-center gap-2 text-sm text-slate-800">
                        <input type="hidden" name="appointment_request_availability[weekly][{{ $dayKey }}][enabled]" value="0">
                        <input
                            type="checkbox"
                            name="appointment_request_availability[weekly][{{ $dayKey }}][enabled]"
                            value="1"
                            class="rounded border-slate-300"
                            @checked(old("appointment_request_availability.weekly.{$dayKey}.enabled", $requestAvailability['weekly'][$dayKey]['enabled'] ?? false))
                        >
                        <span>{{ $dayLabel }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        @php
            $horizonDays = (int) old('appointment_request_availability.horizon_days', $requestAvailability['horizon_days']);
            $horizonIsCustom = ! in_array($horizonDays, [7, 14, 30], true);
        @endphp
        <fieldset class="space-y-2">
            <legend class="text-[10px] font-bold uppercase tracking-wide text-slate-400">How far ahead can customers request?</legend>
            <input type="hidden" name="appointment_request_availability[horizon_is_custom]" value="0">
            @foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days'] as $days => $label)
                <label class="flex items-center gap-2 text-sm text-slate-800">
                    <input
                        type="radio"
                        name="appointment_request_availability[horizon_days]"
                        value="{{ $days }}"
                        class="border-slate-300"
                        @checked(! $horizonIsCustom && $horizonDays === $days)
                        onclick="this.form.elements['appointment_request_availability[horizon_is_custom]'].value='0'"
                    >
                    <span>{{ $label }}</span>
                </label>
            @endforeach
            <label class="flex flex-wrap items-center gap-2 text-sm text-slate-800">
                <input
                    type="radio"
                    name="appointment_request_availability[horizon_days]"
                    value="custom"
                    class="border-slate-300"
                    @checked($horizonIsCustom)
                    onclick="this.form.elements['appointment_request_availability[horizon_is_custom]'].value='1'"
                >
                <span>Custom:</span>
                <input
                    type="number"
                    min="1"
                    max="90"
                    name="appointment_request_availability[horizon_custom_days]"
                    value="{{ old('appointment_request_availability.horizon_custom_days', $horizonIsCustom ? $horizonDays : 21) }}"
                    class="h-8 w-20 rounded-sm border border-slate-300 px-2 text-sm"
                    onfocus="this.form.querySelector('input[name=\'appointment_request_availability[horizon_days]\'][value=custom]').checked=true; this.form.elements['appointment_request_availability[horizon_is_custom]'].value='1'"
                >
                <span class="text-slate-500">days</span>
            </label>
        </fieldset>

        <fieldset class="space-y-2">
            <legend class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Minimum notice</legend>
            @foreach ([0 => 'Same day allowed', 1 => '1 day', 2 => '2 days'] as $days => $label)
                <label class="flex items-center gap-2 text-sm text-slate-800">
                    <input
                        type="radio"
                        name="appointment_request_availability[minimum_notice_days]"
                        value="{{ $days }}"
                        class="border-slate-300"
                        @checked((int) old('appointment_request_availability.minimum_notice_days', $requestAvailability['minimum_notice_days']) === $days)
                    >
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </fieldset>

        @php
            $requestWindows = $requestAvailability['request_windows']
                ?? \App\Ark\Operations\Appointments\ScheduleRequestWindows::forShop($settings);
        @endphp
        <fieldset class="space-y-3">
            <legend class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Appointment request preferences</legend>
            <p class="text-xs leading-5 text-slate-500">
                These settings control the times customers can <strong>request</strong>. Staff still confirms an exact appointment time.
            </p>

            <div class="space-y-3">
                <div class="rounded-sm border border-slate-200 bg-slate-50/60 p-3 space-y-2">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <input type="hidden" name="appointment_request_availability[request_windows][morning][enabled]" value="0">
                        <input type="checkbox" name="appointment_request_availability[request_windows][morning][enabled]" value="1" class="rounded border-slate-300" @checked(old('appointment_request_availability.request_windows.morning.enabled', $requestWindows['morning']['enabled']))>
                        <span>Accept morning requests</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">From</span>
                            <input type="time" name="appointment_request_availability[request_windows][morning][open]" value="{{ old('appointment_request_availability.request_windows.morning.open', $requestWindows['morning']['open']) }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">To</span>
                            <input type="time" name="appointment_request_availability[request_windows][morning][close]" value="{{ old('appointment_request_availability.request_windows.morning.close', $requestWindows['morning']['close']) }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                        </label>
                    </div>
                </div>

                <div class="rounded-sm border border-slate-200 bg-slate-50/60 p-3 space-y-2">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <input type="hidden" name="appointment_request_availability[request_windows][afternoon][enabled]" value="0">
                        <input type="checkbox" name="appointment_request_availability[request_windows][afternoon][enabled]" value="1" class="rounded border-slate-300" @checked(old('appointment_request_availability.request_windows.afternoon.enabled', $requestWindows['afternoon']['enabled']))>
                        <span>Accept afternoon requests</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">From</span>
                            <input type="time" name="appointment_request_availability[request_windows][afternoon][open]" value="{{ old('appointment_request_availability.request_windows.afternoon.open', $requestWindows['afternoon']['open']) }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                        </label>
                        <label class="block">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">To</span>
                            <input type="time" name="appointment_request_availability[request_windows][afternoon][close]" value="{{ old('appointment_request_availability.request_windows.afternoon.close', $requestWindows['afternoon']['close']) }}" class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm">
                        </label>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <input type="hidden" name="appointment_request_availability[request_windows][flexible_enabled]" value="0">
                    <input type="checkbox" name="appointment_request_availability[request_windows][flexible_enabled]" value="1" class="rounded border-slate-300" @checked(old('appointment_request_availability.request_windows.flexible_enabled', $requestWindows['flexible_enabled']))>
                    <span>Accept flexible / any-time requests</span>
                </label>
            </div>
        </fieldset>

        <fieldset class="space-y-2">
            <legend class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Optional latest arrival</legend>
            <p class="text-xs leading-5 text-slate-500">
                Request windows (Morning / Afternoon above) only describe customer preference.
                Confirmed appointments may still use any valid time within shop scheduling hours unless you enable a cutoff here.
            </p>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <input type="hidden" name="appointment_request_availability[request_windows][latest_appointment_arrival_enabled]" value="0">
                <input
                    type="checkbox"
                    name="appointment_request_availability[request_windows][latest_appointment_arrival_enabled]"
                    value="1"
                    class="rounded border-slate-300"
                    @checked(old('appointment_request_availability.request_windows.latest_appointment_arrival_enabled', filled($requestWindows['latest_appointment_arrival'] ?? null)))
                >
                <span>Limit how late staff can schedule appointment starts</span>
            </label>
            <label class="block max-w-xs">
                <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Latest appointment arrival</span>
                <input
                    type="time"
                    name="appointment_request_availability[request_windows][latest_appointment_arrival]"
                    value="{{ old('appointment_request_availability.request_windows.latest_appointment_arrival', $requestWindows['latest_appointment_arrival'] ?? \App\Ark\Operations\Appointments\ScheduleRequestWindows::SUGGESTED_LATEST_ARRIVAL) }}"
                    class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm"
                >
                <span class="mt-0.5 block text-[11px] text-slate-500">Optional. Leave unchecked to allow starts through normal scheduling hours (e.g. close at 6:00 PM).</span>
            </label>
            @error('appointment_request_availability.request_windows')
                <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
        </fieldset>
    </div>

    @include('operations.settings.partials.workstation-presence-settings', ['settings' => $settings])

    <div class="flex items-center gap-3">
        <button type="submit" class="h-9 rounded-sm border border-slate-800 bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">
            Save operations settings
        </button>
        @if ($settings->appointments_enabled)
            <a href="{{ route('operations.appointments.index') }}" class="text-xs font-semibold text-slate-600 underline decoration-slate-300 hover:text-slate-950">
                Open appointment schedule
            </a>
        @endif
    </div>
</form>

@php
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Workstations\Workstation> $scheduleBays */
    $scheduleBays = $scheduleBays ?? collect();
@endphp

<div class="mt-6 max-w-2xl space-y-4">
    <div class="rounded-md border border-slate-200 bg-white p-4">
        <p class="text-sm font-black text-slate-950">Bays</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Operational work locations. They may contribute to schedule-capacity planning. Appointments do not require a bay assignment. Front Counter and phone places stay under Communications → Stations.
        </p>

        <ul class="mt-3 divide-y divide-slate-100">
            @forelse ($scheduleBays as $bay)
                <li class="py-3">
                    <details class="group" @if ((int) old('edit_bay_id') === (int) $bay->id) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
                            <span class="text-sm font-semibold text-slate-950">{{ $bay->displayLocation() }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 group-open:hidden">Edit</span>
                        </summary>
                        <div class="mt-3 space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
                            <form method="POST" action="{{ route('operations.settings.shop.appointments.bays.update', $bay) }}" class="space-y-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="edit_bay_id" value="{{ $bay->id }}">
                                <label class="block">
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Name</span>
                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ old('edit_bay_id') == $bay->id ? old('name', $bay->name) : $bay->name }}"
                                        required
                                        class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm"
                                    >
                                </label>
                                <label class="block">
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Location <span class="font-normal text-slate-500">(optional)</span></span>
                                    <input
                                        type="text"
                                        name="location_label"
                                        value="{{ old('edit_bay_id') == $bay->id ? old('location_label', $bay->location_label) : ($bay->location_label ?? '') }}"
                                        class="mt-0.5 h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm"
                                    >
                                </label>
                                <button type="submit" class="h-8 rounded-sm bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800">
                                    Save bay
                                </button>
                            </form>
                            <form
                                method="POST"
                                action="{{ route('operations.settings.shop.appointments.bays.destroy', $bay) }}"
                                onsubmit="return confirm('Remove {{ $bay->name }} from capacity planning? Open appointments on this bay move to Unassigned. The station row stays for phones if attached.')"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">
                                    Remove from schedule
                                </button>
                            </form>
                        </div>
                    </details>
                </li>
            @empty
                <li class="py-2 text-xs text-slate-500">No bays yet. Add Bay 1 when you want bay capacity or optional bay planning on appointments.</li>
            @endforelse
        </ul>

        <form method="POST" action="{{ route('operations.settings.shop.appointments.bays.store') }}" class="mt-4 space-y-2 border-t border-slate-100 pt-4">
            @csrf
            <p class="text-xs font-semibold text-slate-700">Add a bay</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <input
                    type="text"
                    name="name"
                    value="{{ old('edit_bay_id') ? '' : old('name') }}"
                    required
                    placeholder="Bay 1"
                    class="h-9 rounded-sm border border-slate-300 bg-white px-2 text-sm @error('name') border-rose-400 @enderror"
                >
                <input
                    type="text"
                    name="location_label"
                    value="{{ old('edit_bay_id') ? '' : old('location_label') }}"
                    placeholder="Location (optional)"
                    class="h-9 rounded-sm border border-slate-300 bg-white px-2 text-sm"
                >
            </div>
            @error('name')
                <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
            @enderror
            <button type="submit" class="h-8 rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:bg-slate-50">
                Add bay
            </button>
        </form>
    </div>
</div>
