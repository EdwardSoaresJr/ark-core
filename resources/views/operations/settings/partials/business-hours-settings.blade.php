@php
    $callFlow = \App\Ark\Operations\Telephony\TelephonyCallFlowSettings::fromShopSettings($settings);
    $callFlowConfig = $callFlow->toArray();
    $weekdayLabels = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];
    $timezoneOptions = timezone_identifiers_list();
    $timezoneValue = (string) old('shop_timezone', $settings->shop_timezone ?: \App\Ark\Operations\Settings\ShopSettings::INSTALL_DEFAULT_TIMEZONE);
@endphp

<form method="POST" action="{{ route('operations.settings.shop.business-hours.update') }}" data-settings-business-hours>
    @csrf
    @method('PATCH')

    <div class="alert alert-info" role="status">
        <div class="fw-medium mb-1">How hours relate</div>
        <ul class="mb-0 small">
            <li><strong>Business Hours</strong> (this page) — public open/closed schedule and shop timezone. Used for customer-facing display and phone open/closed routing.</li>
            <li><strong>Appointment scheduling</strong> — follows Business Hours by default; override under Appointments &amp; Setup when booking windows should differ.</li>
            <li><strong>Staff / technician schedules</strong> — individual availability stays under Staff and scheduling tools, not here.</li>
        </ul>
    </div>

    <div class="mb-4" style="max-width: 28rem">
        <label for="settings-business-hours-timezone" class="form-label">Shop timezone</label>
        <select
            id="settings-business-hours-timezone"
            name="shop_timezone"
            required
            @class(['form-select', 'is-invalid' => $errors->has('shop_timezone')])
        >
            @if ($timezoneValue !== '' && ! in_array($timezoneValue, $timezoneOptions, true))
                <option value="{{ $timezoneValue }}" selected>{{ $timezoneValue }} — choose a listed timezone</option>
            @endif
            @foreach ($timezoneOptions as $tz)
                <option value="{{ $tz }}" @selected($timezoneValue === $tz)>{{ $tz }}</option>
            @endforeach
        </select>
        @error('shop_timezone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-hint">Open/closed evaluation and customer-facing clocks use this timezone.</div>
    </div>

    <div class="mb-4">
        <div class="subheader mb-2">Weekly schedule</div>
        <p class="text-secondary small">Toggle a day closed, or set opening and closing times. Times use 24-hour shop local time.</p>
        <div class="list-group">
            @foreach ($weekdayLabels as $dayKey => $dayLabel)
                @php
                    $dayHours = $callFlowConfig['weekly_hours'][$dayKey] ?? ['enabled' => false, 'open' => '09:00', 'close' => '18:00'];
                @endphp
                <div class="list-group-item py-2" data-business-hours-day="{{ $dayKey }}">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-sm-3">
                            <label class="form-check mb-0">
                                <input type="hidden" name="telephony_call_flow[weekly_hours][{{ $dayKey }}][enabled]" value="0">
                                <input
                                    type="checkbox"
                                    name="telephony_call_flow[weekly_hours][{{ $dayKey }}][enabled]"
                                    value="1"
                                    @checked(old("telephony_call_flow.weekly_hours.{$dayKey}.enabled", $dayHours['enabled']))
                                    class="form-check-input"
                                >
                                <span class="form-check-label fw-medium">{{ $dayLabel }}</span>
                            </label>
                        </div>
                        <div class="col-sm-2 d-none d-sm-block small text-secondary">Open hours</div>
                        <div class="col-6 col-sm-3">
                            <input
                                type="time"
                                step="60"
                                name="telephony_call_flow[weekly_hours][{{ $dayKey }}][open]"
                                value="{{ old("telephony_call_flow.weekly_hours.{$dayKey}.open", $dayHours['open']) }}"
                                class="form-control"
                                aria-label="{{ $dayLabel }} open"
                            >
                        </div>
                        <div class="col-6 col-sm-3">
                            <input
                                type="time"
                                step="60"
                                name="telephony_call_flow[weekly_hours][{{ $dayKey }}][close]"
                                value="{{ old("telephony_call_flow.weekly_hours.{$dayKey}.close", $dayHours['close']) }}"
                                class="form-control"
                                aria-label="{{ $dayLabel }} close"
                            >
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @error('telephony_call_flow.weekly_hours')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-4">
        <label for="settings-business-hours-closed-dates" class="form-label">Holiday closures (one YYYY-MM-DD per line)</label>
        <textarea
            id="settings-business-hours-closed-dates"
            name="telephony_call_flow[closed_dates]"
            rows="3"
            class="form-control"
            placeholder="2026-12-25&#10;2026-11-27"
        >{{ old('telephony_call_flow.closed_dates', implode("\n", $callFlowConfig['closed_dates'] ?? [])) }}</textarea>
        <div class="form-hint">Full-day closures in addition to the weekly schedule. Also used for phone closed routing.</div>
    </div>

    <div class="text-end">
        <button type="submit" class="btn btn-primary">Save Business Hours</button>
    </div>
</form>
