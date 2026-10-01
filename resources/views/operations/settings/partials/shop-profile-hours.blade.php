@php
    /** @var \App\Ark\Operations\Settings\ShopSettings $settings */
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
@endphp

<div class="mt-6 space-y-3 rounded-md border border-slate-200 bg-slate-50/60 p-4">
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Business hours</p>
        <p class="mt-0.5 text-xs leading-5 text-slate-500">
            Shop open hours and holidays. ARK Voice and customer messaging use this schedule from Shop Profile.
        </p>
    </div>

    <div class="space-y-1">
        @foreach ($weekdayLabels as $dayKey => $dayLabel)
            @php $dayHours = $callFlowConfig['weekly_hours'][$dayKey] ?? ['enabled' => false, 'open' => '09:00', 'close' => '18:00']; @endphp
            <div class="grid gap-2 rounded-sm border border-slate-200 bg-white px-2 py-2 sm:grid-cols-[7rem_5rem_1fr_1fr] sm:items-center">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                    <input type="hidden" name="telephony_call_flow[weekly_hours][{{ $dayKey }}][enabled]" value="0">
                    <input
                        type="checkbox"
                        name="telephony_call_flow[weekly_hours][{{ $dayKey }}][enabled]"
                        value="1"
                        @checked(old("telephony_call_flow.weekly_hours.{$dayKey}.enabled", $dayHours['enabled']))
                    >
                    {{ $dayLabel }}
                </label>
                <span class="hidden text-[10px] font-bold uppercase tracking-wide text-slate-400 sm:block">Open hours</span>
                <input
                    type="time"
                    step="60"
                    name="telephony_call_flow[weekly_hours][{{ $dayKey }}][open]"
                    value="{{ old("telephony_call_flow.weekly_hours.{$dayKey}.open", $dayHours['open']) }}"
                    class="h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                >
                <input
                    type="time"
                    step="60"
                    name="telephony_call_flow[weekly_hours][{{ $dayKey }}][close]"
                    value="{{ old("telephony_call_flow.weekly_hours.{$dayKey}.close", $dayHours['close']) }}"
                    class="h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                >
            </div>
        @endforeach
    </div>

    <label class="block">
        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Holiday closures (one YYYY-MM-DD per line)</span>
        <textarea
            name="telephony_call_flow[closed_dates]"
            rows="3"
            class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
            placeholder="2026-12-25&#10;2026-11-27"
        >{{ old('telephony_call_flow.closed_dates', implode("\n", $callFlowConfig['closed_dates'])) }}</textarea>
    </label>
</div>
