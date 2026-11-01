@props([
    'value' => null,
    'durationMinutes' => null,
])

@php
    use App\Ark\Operations\Appointments\AppointmentSlotMinutes;

    $overrideRaw = old('estimated_labor_hours', $value);
    $isManual = filled($overrideRaw) && $overrideRaw !== '';
    $durationMinutes = (int) ($durationMinutes ?: AppointmentSlotMinutes::resolve());
    $durationMinutes = AppointmentSlotMinutes::snapDurationMinutes($durationMinutes);
    $inferredHours = max(0.25, round($durationMinutes / 60, 2));
    $inferredLabel = rtrim(rtrim(number_format($inferredHours, 2, '.', ''), '0'), '.');
@endphp

<div
    {{ $attributes->class('form-fieldset mb-0') }}
    data-appointment-reserved-labor
    x-data="{
        manual: @js($isManual),
        overrideHours: @js($isManual ? (string) $overrideRaw : ''),
        durationMinutes: @js($durationMinutes),
        init() {
            const form = this.$el.closest('form');
            const sel = form?.querySelector('select[name=duration_minutes]');
            if (! sel) {
                return;
            }

            const sync = () => {
                const next = parseInt(sel.value, 10);
                if (Number.isFinite(next) && next > 0) {
                    this.durationMinutes = next;
                }
            };

            sync();
            sel.addEventListener('change', sync);
        },
        get inferredHours() {
            const hours = this.durationMinutes / 60;

            return Math.max(0.25, Math.round(hours * 100) / 100);
        },
        formatHours(value) {
            const n = Number(value);
            if (! Number.isFinite(n)) {
                return '';
            }

            const rounded = Math.round(n * 100) / 100;

            return String(rounded) + ' hr';
        },
        enableOverride() {
            this.manual = true;
            this.overrideHours = String(this.inferredHours);
            this.$nextTick(() => this.$refs.laborInput?.focus());
        },
        resetToAuto() {
            this.manual = false;
            this.overrideHours = '';
        },
    }"
>
    <div class="subheader mb-2">Reserved labor</div>

    <div x-show="! manual">
        <div class="fw-medium">
            <span x-text="formatHours(inferredHours)">{{ $inferredLabel }} hr</span>
            <span class="fw-normal text-secondary"> · Auto</span>
        </div>
        <div class="small text-secondary mt-1 mb-2">Automatically matches appointment length unless you override it.</div>
        <button type="button" class="btn" @click="enableOverride()">Override</button>
    </div>

    <div x-show="manual" @unless ($isManual) x-cloak @endunless>
        <div class="row g-2 align-items-center">
            <div class="col-5 col-sm-3">
                <input
                    type="text"
                    inputmode="decimal"
                    name="estimated_labor_hours"
                    aria-label="Reserved labor hours"
                    x-ref="laborInput"
                    x-model="overrideHours"
                    data-numeric-only
                    value="{{ $isManual ? $overrideRaw : '' }}"
                    @unless ($isManual) disabled @endunless
                    x-bind:disabled="! manual"
                    @class(['form-control', 'is-invalid' => $errors->has('estimated_labor_hours')])
                >
            </div>
            <div class="col-auto text-secondary">
                <span x-text="formatHours(overrideHours)">{{ $isManual ? $inferredLabel : '' }}</span>
                <span> · Manual</span>
            </div>
        </div>
        <div class="small text-secondary mt-1 mb-2">Use this when the job will consume more or less shop capacity than the appointment block.</div>
        <button type="button" class="btn" @click="resetToAuto()">Reset to appointment length</button>
    </div>

    {{-- Auto mode submits blank; disabled when Manual so only the override input posts. --}}
    <input
        type="hidden"
        name="estimated_labor_hours"
        value=""
        @if ($isManual) disabled @endif
        x-bind:disabled="manual"
    >

    @error('estimated_labor_hours')
        <div class="small fw-medium text-danger mt-1">{{ $message }}</div>
    @enderror
</div>
