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
    {{ $attributes->class('rounded-sm border border-slate-200 bg-slate-50/60 px-3 py-2') }}
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
    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Reserved labor</p>

    <div x-show="! manual" class="mt-1 space-y-1.5">
        <p class="text-sm font-semibold text-slate-950">
            <span x-text="formatHours(inferredHours)">{{ $inferredLabel }} hr</span>
            <span class="font-normal text-slate-500"> · Auto</span>
        </p>
        <p class="text-[11px] text-slate-600">Automatically matches appointment length unless you override it.</p>
        <button
            type="button"
            class="h-8 border border-slate-300 bg-white px-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-800 hover:bg-slate-50"
            @click="enableOverride()"
        >
            Override
        </button>
    </div>

    <div x-show="manual" @unless ($isManual) x-cloak @endunless class="mt-1 space-y-1.5">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <label class="block w-28">
                <input
                    type="text"
                    inputmode="decimal"
                    name="estimated_labor_hours"
                    x-ref="laborInput"
                    x-model="overrideHours"
                    data-numeric-only
                    value="{{ $isManual ? $overrideRaw : '' }}"
                    @unless ($isManual) disabled @endunless
                    x-bind:disabled="! manual"
                    class="h-9 w-full rounded-sm border border-slate-300 bg-white px-2 text-sm @error('estimated_labor_hours') border-rose-400 @enderror"
                >
            </label>
            <p class="text-sm text-slate-600">
                <span x-text="formatHours(overrideHours)">{{ $isManual ? $inferredLabel : '' }}</span>
                <span> · Manual</span>
            </p>
        </div>
        <p class="text-[11px] text-slate-600">Use this when the job will consume more or less shop capacity than the appointment block.</p>
        <button
            type="button"
            class="h-8 border border-slate-300 bg-white px-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-800 hover:bg-slate-50"
            @click="resetToAuto()"
        >
            Reset to appointment length
        </button>
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
        <p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>
    @enderror
</div>
