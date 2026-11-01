<form method="POST" action="{{ route('operations.settings.shop.appointments.update') }}" style="max-width: 48rem" data-settings-appointments>
    @csrf
    @method('PATCH')

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
            <h4 class="mb-1">Enable Appointments</h4>
            <p class="text-secondary small mb-0">
                Turn scheduling on when staff are trained and appointments have a clear path into Check In or repair orders.
                Disabled by default - Work stays the advisor front door until you are ready.
            </p>
        </div>
        <label class="form-check form-switch mb-0 flex-shrink-0">
            <input type="hidden" name="appointments_enabled" value="0">
            <input
                type="checkbox"
                name="appointments_enabled"
                value="1"
                class="form-check-input"
                @checked(old('appointments_enabled', $settings->appointments_enabled))
            >
            <span class="form-check-label">{{ $settings->appointments_enabled ? 'Enabled' : 'Disabled' }}</span>
        </label>
    </div>

    <div class="border-top pt-3 mt-3">
        <h4 class="mb-1">Appointment time steps</h4>
        <p class="text-secondary small">
            Schedule grid and book/edit time pickers use this increment - no scrolling minute-by-minute.
        </p>
        <div style="max-width: 20rem">
            <label for="settings-appointment-slot-minutes" class="form-label">Increment</label>
            <select id="settings-appointment-slot-minutes" name="appointment_slot_minutes" class="form-select">
                @foreach (\App\Ark\Operations\Appointments\AppointmentSlotMinutes::settingOptions() as $minutes => $label)
                    <option value="{{ $minutes }}" @selected((int) old('appointment_slot_minutes', $settings->appointment_slot_minutes ?? 30) === $minutes)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="border-top pt-3 mt-3">
        <h4 class="mb-1">Scheduling capacity</h4>
        <p class="text-secondary small">
            Appointments reserve soft shop capacity. Bay and technician assignments are optional planning - not required to book.
        </p>

        <fieldset class="mb-3">
            <legend class="form-label">Capacity basis</legend>
            @foreach (\App\Ark\Operations\Appointments\AppointmentCapacityBasis::cases() as $basis)
                <label class="form-check">
                    <input
                        type="radio"
                        name="appointment_capacity_basis"
                        value="{{ $basis->value }}"
                        class="form-check-input"
                        @checked(old('appointment_capacity_basis', $settings->appointment_capacity_basis ?? 'limiting_resource') === $basis->value)
                    >
                    <span class="form-check-label">{{ $basis->label() }}</span>
                </label>
            @endforeach
        </fieldset>

        <div class="mb-3" style="max-width: 20rem">
            <label for="settings-appointment-scheduling-target" class="form-label">Scheduling target</label>
            <div @class(['input-group', 'has-validation' => $errors->has('appointment_scheduling_target_percent')])>
                <input
                    id="settings-appointment-scheduling-target"
                    type="number"
                    name="appointment_scheduling_target_percent"
                    min="25"
                    max="300"
                    value="{{ old('appointment_scheduling_target_percent', $settings->appointment_scheduling_target_percent ?? 100) }}"
                    @class(['form-control', 'is-invalid' => $errors->has('appointment_scheduling_target_percent')])
                >
                <span class="input-group-text">%</span>
                @error('appointment_scheduling_target_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-hint">100% matches base capacity. Above 100% intentionally overpacks the day.</div>
        </div>

        <fieldset>
            <legend class="form-label">When target is exceeded</legend>
            @foreach (\App\Ark\Operations\Appointments\AppointmentCapacityEnforcement::cases() as $enforcement)
                <label class="form-check">
                    <input
                        type="radio"
                        name="appointment_capacity_enforcement"
                        value="{{ $enforcement->value }}"
                        class="form-check-input"
                        @checked(old('appointment_capacity_enforcement', $settings->appointment_capacity_enforcement ?? 'warn') === $enforcement->value)
                    >
                    <span class="form-check-label">{{ $enforcement->label() }}</span>
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
        class="border-top pt-3 mt-3"
        x-data="{ followShop: {{ $followShopHours ? 'true' : 'false' }} }"
    >
        <h4 class="mb-1">Scheduling hours</h4>
        <p class="text-secondary small">
            Staff appointment booking follows shop Business Hours by default.
            Customize only when scheduling should blacklist a day or use narrower windows than the phones.
        </p>

        <label class="form-check">
            <input type="hidden" name="scheduling_hours_follow_shop" value="0">
            <input
                type="checkbox"
                name="scheduling_hours_follow_shop"
                value="1"
                class="form-check-input"
                x-model="followShop"
            >
            <span class="form-check-label">Follow Business Hours</span>
            <span class="form-check-description">
                {{ $businessHoursSummary }}
                ·
                <a
                    href="{{ route('operations.settings.shop.edit', ['section' => 'general']) }}"
                >Edit Business Hours</a>
            </span>
        </label>

        <div x-show="!followShop" x-cloak class="mt-3">
            <div class="subheader mb-1">Custom scheduling windows</div>
            <p class="text-secondary small">Uncheck a day to blacklist it for appointments. Open/close may be narrower than Business Hours.</p>
            <div class="list-group">
                @foreach ($weekdayLabels as $dayKey => $dayLabel)
                    @php
                        $dayHours = $customSchedulingHours[$dayKey] ?? ['enabled' => false, 'open' => '08:00', 'close' => '17:00'];
                    @endphp
                    <div class="list-group-item" data-scheduling-day="{{ $dayKey }}">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-sm-3">
                                <label class="form-check mb-0">
                                    <input type="hidden" name="scheduling_hours[{{ $dayKey }}][enabled]" value="0">
                                    <input
                                        type="checkbox"
                                        name="scheduling_hours[{{ $dayKey }}][enabled]"
                                        value="1"
                                        class="form-check-input"
                                        @checked(old("scheduling_hours.{$dayKey}.enabled", $dayHours['enabled'] ?? false))
                                    >
                                    <span class="form-check-label fw-medium">{{ $dayLabel }}</span>
                                </label>
                            </div>
                            <div class="col-6 col-sm">
                                <input
                                    type="time"
                                    name="scheduling_hours[{{ $dayKey }}][open]"
                                    value="{{ old("scheduling_hours.{$dayKey}.open", $dayHours['open'] ?? '08:00') }}"
                                    aria-label="{{ $dayLabel }} opens"
                                    class="form-control"
                                >
                            </div>
                            <div class="col-6 col-sm">
                                <input
                                    type="time"
                                    name="scheduling_hours[{{ $dayKey }}][close]"
                                    value="{{ old("scheduling_hours.{$dayKey}.close", $dayHours['close'] ?? '17:00') }}"
                                    aria-label="{{ $dayLabel }} closes"
                                    class="form-control"
                                >
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('scheduling_hours')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>

    @php
        $requestAvailability = \App\Ark\Operations\Appointments\AppointmentRequestAvailability::forShop($settings);
    @endphp

    <div class="border-top pt-3 mt-3">
        <h4 class="mb-1">Appointment Request Availability</h4>
        <p class="text-secondary small">
            Controls which days appointment requests may offer. Public booking presentation belongs to ARK Website.
            Independent of Business Hours and staff Scheduling hours above.
        </p>

        <fieldset class="mb-3">
            <legend class="form-label">Accept requests</legend>
            <div class="row g-2">
                @foreach ($weekdayLabels as $dayKey => $dayLabel)
                    <div class="col-6 col-sm-3">
                        <label class="form-check mb-0">
                            <input type="hidden" name="appointment_request_availability[weekly][{{ $dayKey }}][enabled]" value="0">
                            <input
                                type="checkbox"
                                name="appointment_request_availability[weekly][{{ $dayKey }}][enabled]"
                                value="1"
                                class="form-check-input"
                                @checked(old("appointment_request_availability.weekly.{$dayKey}.enabled", $requestAvailability['weekly'][$dayKey]['enabled'] ?? false))
                            >
                            <span class="form-check-label">{{ $dayLabel }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
        </fieldset>

        @php
            $horizonDays = (int) old('appointment_request_availability.horizon_days', $requestAvailability['horizon_days']);
            $horizonIsCustom = ! in_array($horizonDays, [7, 14, 30], true);
        @endphp
        <fieldset class="mb-3">
            <legend class="form-label">How far ahead can customers request?</legend>
            <input type="hidden" name="appointment_request_availability[horizon_is_custom]" value="0">
            @foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days'] as $days => $label)
                <label class="form-check">
                    <input
                        type="radio"
                        name="appointment_request_availability[horizon_days]"
                        value="{{ $days }}"
                        class="form-check-input"
                        @checked(! $horizonIsCustom && $horizonDays === $days)
                        onclick="this.form.elements['appointment_request_availability[horizon_is_custom]'].value='0'"
                    >
                    <span class="form-check-label">{{ $label }}</span>
                </label>
            @endforeach
            <label class="form-check mb-0">
                <input
                    type="radio"
                    name="appointment_request_availability[horizon_days]"
                    value="custom"
                    class="form-check-input"
                    @checked($horizonIsCustom)
                    onclick="this.form.elements['appointment_request_availability[horizon_is_custom]'].value='1'"
                >
                <span class="form-check-label d-inline-flex flex-wrap align-items-center gap-2">
                    Custom:
                    <input
                        type="number"
                        min="1"
                        max="90"
                        name="appointment_request_availability[horizon_custom_days]"
                        value="{{ old('appointment_request_availability.horizon_custom_days', $horizonIsCustom ? $horizonDays : 21) }}"
                        aria-label="Custom request horizon in days"
                        class="form-control form-control-sm"
                        style="width: 5rem"
                        onfocus="this.form.querySelector('input[name=\'appointment_request_availability[horizon_days]\'][value=custom]').checked=true; this.form.elements['appointment_request_availability[horizon_is_custom]'].value='1'"
                    >
                    <span class="text-secondary">days</span>
                </span>
            </label>
        </fieldset>

        <fieldset class="mb-3">
            <legend class="form-label">Minimum notice</legend>
            @foreach ([0 => 'Same day allowed', 1 => '1 day', 2 => '2 days'] as $days => $label)
                <label class="form-check">
                    <input
                        type="radio"
                        name="appointment_request_availability[minimum_notice_days]"
                        value="{{ $days }}"
                        class="form-check-input"
                        @checked((int) old('appointment_request_availability.minimum_notice_days', $requestAvailability['minimum_notice_days']) === $days)
                    >
                    <span class="form-check-label">{{ $label }}</span>
                </label>
            @endforeach
        </fieldset>

        @php
            $requestWindows = $requestAvailability['request_windows']
                ?? \App\Ark\Operations\Appointments\ScheduleRequestWindows::forShop($settings);
        @endphp
        <fieldset class="mb-3">
            <legend class="form-label">Appointment request preferences</legend>
            <p class="text-secondary small">
                These settings control the times customers can <strong>request</strong>. Staff still confirms an exact appointment time.
            </p>

            <div class="list-group mb-3">
                @foreach (['morning' => 'Accept morning requests', 'afternoon' => 'Accept afternoon requests'] as $windowKey => $windowLabel)
                    <div class="list-group-item" data-request-window="{{ $windowKey }}">
                        <label class="form-check mb-2">
                            <input type="hidden" name="appointment_request_availability[request_windows][{{ $windowKey }}][enabled]" value="0">
                            <input type="checkbox" name="appointment_request_availability[request_windows][{{ $windowKey }}][enabled]" value="1" class="form-check-input" @checked(old('appointment_request_availability.request_windows.'.$windowKey.'.enabled', $requestWindows[$windowKey]['enabled']))>
                            <span class="form-check-label fw-medium">{{ $windowLabel }}</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="settings-request-window-{{ $windowKey }}-open" class="form-label">From</label>
                                <input id="settings-request-window-{{ $windowKey }}-open" type="time" name="appointment_request_availability[request_windows][{{ $windowKey }}][open]" value="{{ old('appointment_request_availability.request_windows.'.$windowKey.'.open', $requestWindows[$windowKey]['open']) }}" class="form-control">
                            </div>
                            <div class="col-6">
                                <label for="settings-request-window-{{ $windowKey }}-close" class="form-label">To</label>
                                <input id="settings-request-window-{{ $windowKey }}-close" type="time" name="appointment_request_availability[request_windows][{{ $windowKey }}][close]" value="{{ old('appointment_request_availability.request_windows.'.$windowKey.'.close', $requestWindows[$windowKey]['close']) }}" class="form-control">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <label class="form-check mb-0">
                <input type="hidden" name="appointment_request_availability[request_windows][flexible_enabled]" value="0">
                <input type="checkbox" name="appointment_request_availability[request_windows][flexible_enabled]" value="1" class="form-check-input" @checked(old('appointment_request_availability.request_windows.flexible_enabled', $requestWindows['flexible_enabled']))>
                <span class="form-check-label">Accept flexible / any-time requests</span>
            </label>
        </fieldset>

        <fieldset>
            <legend class="form-label">Optional latest arrival</legend>
            <p class="text-secondary small">
                Request windows (Morning / Afternoon above) only describe customer preference.
                Confirmed appointments may still use any valid time within shop scheduling hours unless you enable a cutoff here.
            </p>
            <label class="form-check">
                <input type="hidden" name="appointment_request_availability[request_windows][latest_appointment_arrival_enabled]" value="0">
                <input
                    type="checkbox"
                    name="appointment_request_availability[request_windows][latest_appointment_arrival_enabled]"
                    value="1"
                    class="form-check-input"
                    @checked(old('appointment_request_availability.request_windows.latest_appointment_arrival_enabled', filled($requestWindows['latest_appointment_arrival'] ?? null)))
                >
                <span class="form-check-label">Limit how late staff can schedule appointment starts</span>
            </label>
            <div style="max-width: 20rem">
                <label for="settings-latest-appointment-arrival" class="form-label">Latest appointment arrival</label>
                <input
                    id="settings-latest-appointment-arrival"
                    type="time"
                    name="appointment_request_availability[request_windows][latest_appointment_arrival]"
                    value="{{ old('appointment_request_availability.request_windows.latest_appointment_arrival', $requestWindows['latest_appointment_arrival'] ?? \App\Ark\Operations\Appointments\ScheduleRequestWindows::SUGGESTED_LATEST_ARRIVAL) }}"
                    class="form-control"
                >
                <div class="form-hint">Optional. Leave unchecked to allow starts through normal scheduling hours (e.g. close at 6:00 PM).</div>
            </div>
            @error('appointment_request_availability.request_windows')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </fieldset>
    </div>

    @include('operations.settings.partials.workstation-presence-settings', ['settings' => $settings])

    <div class="d-flex flex-wrap align-items-center justify-content-end gap-3 border-top pt-3 mt-3">
        @if ($settings->appointments_enabled)
            <a href="{{ route('operations.appointments.index') }}">
                Open appointment schedule
            </a>
        @endif
        <button type="submit" class="btn btn-primary">
            Save appointment settings
        </button>
    </div>
</form>

@php
    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\Workstations\Workstation> $scheduleBays */
    $scheduleBays = $scheduleBays ?? collect();
@endphp

<div class="border-top pt-4 mt-4" style="max-width: 48rem" data-settings-bays>
    <h4 class="mb-1">Bays</h4>
    <p class="text-secondary small">
        Operational work locations. They may contribute to schedule-capacity planning. Appointments do not require a bay assignment. Front Counter and phone places stay under Communications → Stations.
    </p>

    <div class="list-group mb-3">
        @forelse ($scheduleBays as $bay)
            <div class="list-group-item" data-settings-bay="{{ $bay->id }}">
                <details @if ((int) old('edit_bay_id') === (int) $bay->id) open @endif>
                    <summary class="d-flex align-items-center justify-content-between gap-3" style="cursor: pointer; list-style: none">
                        <span class="fw-medium">{{ $bay->displayLocation() }}</span>
                        <span class="btn btn-sm">Edit</span>
                    </summary>
                    <div class="border-top pt-3 mt-3">
                        <form method="POST" action="{{ route('operations.settings.shop.appointments.bays.update', $bay) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="edit_bay_id" value="{{ $bay->id }}">
                            <div class="row g-2">
                                <div class="col-12 col-sm-6">
                                    <label for="settings-bay-{{ $bay->id }}-name" class="form-label required">Name</label>
                                    <input
                                        id="settings-bay-{{ $bay->id }}-name"
                                        type="text"
                                        name="name"
                                        value="{{ old('edit_bay_id') == $bay->id ? old('name', $bay->name) : $bay->name }}"
                                        required
                                        class="form-control"
                                    >
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label for="settings-bay-{{ $bay->id }}-location" class="form-label">Location <span class="text-secondary fw-normal">(optional)</span></label>
                                    <input
                                        id="settings-bay-{{ $bay->id }}-location"
                                        type="text"
                                        name="location_label"
                                        value="{{ old('edit_bay_id') == $bay->id ? old('location_label', $bay->location_label) : ($bay->location_label ?? '') }}"
                                        class="form-control"
                                    >
                                </div>
                            </div>
                            <div class="mt-2">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    Save bay
                                </button>
                            </div>
                        </form>
                        <form
                            method="POST"
                            action="{{ route('operations.settings.shop.appointments.bays.destroy', $bay) }}"
                            onsubmit="return confirm('Remove {{ $bay->name }} from capacity planning? Open appointments on this bay move to Unassigned. The station row stays for phones if attached.')"
                            class="mt-2"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-ghost-danger">
                                Remove from schedule
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        @empty
            <div class="list-group-item text-secondary small">No bays yet. Add Bay 1 when you want bay capacity or optional bay planning on appointments.</div>
        @endforelse
    </div>

    <form method="POST" action="{{ route('operations.settings.shop.appointments.bays.store') }}" class="card card-sm">
        @csrf
        <div class="card-body">
            <div class="subheader mb-2">Add a bay</div>
            <div class="row g-2">
                <div class="col-12 col-sm-6">
                    <input
                        type="text"
                        name="name"
                        value="{{ old('edit_bay_id') ? '' : old('name') }}"
                        required
                        placeholder="Bay 1"
                        aria-label="Bay name"
                        @class(['form-control', 'is-invalid' => $errors->has('name')])
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-sm-6">
                    <input
                        type="text"
                        name="location_label"
                        value="{{ old('edit_bay_id') ? '' : old('location_label') }}"
                        placeholder="Location (optional)"
                        aria-label="Bay location"
                        class="form-control"
                    >
                </div>
            </div>
            <div class="mt-2">
                <button type="submit" class="btn btn-sm">
                    Add bay
                </button>
            </div>
        </div>
    </form>
</div>
