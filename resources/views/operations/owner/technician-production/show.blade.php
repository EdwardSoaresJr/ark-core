@php
    $a = $assist;
    $money = fn (?int $cents): string => $cents === null ? '—' : '$'.number_format($cents / 100, 2);
@endphp

<x-operations.app :title="'Production · '.$technician->name">
    <section class="mx-auto max-w-5xl space-y-4 px-3 py-4 sm:px-4">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Technician production</p>
                <h1 class="text-xl font-black text-slate-950">{{ $technician->name }}</h1>
                <p class="mt-1 text-xs text-slate-500">{{ $from }} → {{ $to }}</p>
                <a href="{{ route('operations.owner.technician-production.index', ['from' => $from, 'to' => $to]) }}" class="mt-2 inline-block text-xs font-bold text-slate-700 underline">← All technicians</a>
                <a href="{{ route('operations.time-clock.staff', $technician) }}" class="mt-2 ml-3 inline-block text-xs font-bold text-slate-700 underline">Time clock</a>
            </div>
            <form method="GET" action="{{ route('operations.owner.technician-production.show', $technician) }}" class="flex flex-wrap items-end gap-2">
                <label class="block text-xs font-semibold text-slate-700">From <x-operations.date-field name="from" :value="$from" class="mt-1" /></label>
                <label class="block text-xs font-semibold text-slate-700">To <x-operations.date-field name="to" :value="$to" class="mt-1" /></label>
                <button type="submit" class="ops-index-btn ops-index-btn--ghost">Update</button>
            </form>
        </div>

        @if (session('status'))
            <p class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-900">{{ session('status') }}</p>
        @endif

        @if ($a['overtime_review_required'])
            <div class="border-2 border-amber-500 bg-amber-50 px-3 py-3 text-amber-950">
                <p class="text-sm font-black uppercase tracking-[0.06em]">Overtime review required</p>
                <p class="mt-1 text-sm font-semibold">{{ $a['overtime_warning']['message'] }}</p>
                <ul class="mt-2 list-disc pl-5 text-xs">
                    @foreach ($a['overtime_warning']['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($a['history_unavailable'])
            <div class="border border-slate-300 bg-slate-50 px-3 py-3 text-sm text-slate-800">
                <p class="font-black">Production history unavailable for this period</p>
                <p class="mt-1 text-xs text-slate-600">{{ $a['history_unavailable_reason'] }}</p>
                <p class="mt-2 text-xs text-slate-500">Zero and unknown are different. ARK will not pretend recognized flag was 0.0 before recognition authority existed.</p>
            </div>
        @else
            @if ($a['history_partial'] ?? false)
                <p class="border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">{{ $a['history_partial_note'] }}</p>
            @endif

            {{-- Production picture first --}}
            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Production picture</p>
                    <p class="text-xs text-slate-500">Why is recognized flag low — pending still sitting, or not much production?</p>
                </div>
                <div class="grid gap-px bg-slate-200 sm:grid-cols-3">
                    <div class="bg-white px-3 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Clock hours</p>
                        <p class="mt-1 text-2xl font-black tabular-nums text-slate-950">{{ number_format((float) $a['clock_hours'], 1) }}</p>
                    </div>
                    <div class="bg-white px-3 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Recognized flag</p>
                        <p class="mt-1 text-2xl font-black tabular-nums text-slate-950">{{ number_format((float) $a['recognized_flag_hours'], 1) }}</p>
                    </div>
                    <div class="bg-white px-3 py-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Pending flag</p>
                        <p class="mt-1 text-2xl font-black tabular-nums text-slate-950">{{ number_format((float) $a['pending_flag_hours'], 1) }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Not earned · not owed</p>
                    </div>
                </div>
                <div class="grid gap-px border-t border-slate-200 bg-slate-200 sm:grid-cols-2">
                    <div class="bg-white px-3 py-2.5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Recognized / clock</p>
                        <p class="text-lg font-black tabular-nums">{{ $a['recognized_efficiency_percent'] === null ? '—' : number_format((float) $a['recognized_efficiency_percent'], 1).'%' }}</p>
                    </div>
                    <div class="bg-white px-3 py-2.5">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $a['production_in_view_label'] }}</p>
                        <p class="text-lg font-black tabular-nums">{{ $a['production_in_view_percent'] === null ? '—' : number_format((float) $a['production_in_view_percent'], 1).'%' }}</p>
                        <p class="text-[11px] text-slate-500">Includes pending — not earned production</p>
                    </div>
                </div>
            </div>

            @if ($a['wip_impact'] ?? null)
                <p class="border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800">{{ $a['wip_impact']['message'] }}</p>
            @endif

            {{-- Dollars secondary --}}
            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Base compensation assist</p>
                    <p class="text-xs text-slate-500">Management estimate only — not Gross Pay, Paycheck, or Payroll Due.</p>
                </div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-slate-600">Recognized flag earnings</dt>
                        <dd class="font-semibold tabular-nums">{{ $money($a['recognized_earnings_cents']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-slate-600">Floor requirement</dt>
                        <dd class="font-semibold tabular-nums">{{ $money($a['floor_requirement_cents']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-slate-600">Floor exposure</dt>
                        <dd class="font-semibold tabular-nums">{{ $money($a['floor_exposure_cents']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2.5">
                        <dt class="font-bold text-slate-900">Base compensation assist</dt>
                        <dd class="text-lg font-black tabular-nums text-slate-950">{{ $money($a['base_compensation_assist_cents']) }}</dd>
                    </div>
                </dl>
                @if ($a['pending_flag_hours'] > 0)
                    <div class="border-t border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                        <p><span class="font-semibold">{{ number_format((float) $a['pending_flag_hours'], 1) }} pending flag hr</span>
                            @if ($a['pending_value_cents'] !== null)
                                · about {{ $money($a['pending_value_cents']) }} of potential flag production sitting unfinished at the current flag rate.
                            @endif
                        </p>
                        <p class="mt-1 text-slate-500">Not wages owed. Not unpaid compensation. Not guaranteed future pay. Pending does not reduce floor exposure.</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Compensable time entry --}}
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Compensable hours</p>
                <p class="text-xs text-slate-500">Manual override · locks punch-derived hours for that day · leave blank to clear override and restore punch hours</p>
            </div>
            <form method="POST" action="{{ route('operations.owner.technician-production.time', $technician) }}" class="px-3 py-3">
                @csrf
                <input type="hidden" name="from" value="{{ $from }}">
                <input type="hidden" name="to" value="{{ $to }}">
                <div class="grid gap-2 sm:grid-cols-4 md:grid-cols-7">
                    @foreach ($weekDates as $date)
                        @php
                            $label = \Illuminate\Support\Carbon::parse($date)->format('D n/j');
                            $val = $timeByDate[$date] ?? null;
                        @endphp
                        <label class="block text-[11px] font-semibold text-slate-700">
                            {{ $label }}
                            <input
                                type="number"
                                name="hours[{{ $date }}]"
                                value="{{ $val !== null ? number_format((float) $val, 2, '.', '') : '' }}"
                                min="0"
                                max="24"
                                step="0.25"
                                class="mt-1 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm tabular-nums"
                                placeholder="0"
                            >
                        </label>
                    @endforeach
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="ops-index-btn ops-index-btn--primary">Save hours</button>
                </div>
            </form>
        </div>

        {{-- Show me --}}
        <details class="border border-slate-300 bg-white" open>
            <summary class="cursor-pointer list-none px-3 py-2 text-sm font-bold text-slate-900 marker:content-none [&::-webkit-details-marker]:hidden">
                Show me · audit trail
            </summary>
            <div class="space-y-4 border-t border-slate-200 px-3 py-3 text-sm">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Time</p>
                    <ul class="mt-1 divide-y divide-slate-100 border border-slate-100">
                        @foreach ($a['daily_time'] as $day)
                            <li class="flex flex-wrap justify-between gap-2 px-2 py-1.5 text-xs">
                                <span>{{ $day['weekday'] }} {{ $day['date'] }}</span>
                                <span class="tabular-nums">{{ $day['compensable_hours'] === null ? '—' : number_format((float) $day['compensable_hours'], 2).' hr' }}
                                    @if ($day['floor_rate_dollars'] !== null)
                                        · floor ${{ number_format((float) $day['floor_rate_dollars'], 2) }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @unless ($a['history_unavailable'])
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Recognized flag</p>
                        @forelse ($a['recognized_lines'] as $line)
                            <div class="mt-1 border border-slate-100 px-2 py-1.5 text-xs">
                                <p class="font-semibold text-slate-900">RO {{ $line['repair_order_id'] }} · {{ $line['vehicle'] }} · {{ number_format((float) $line['flag_hours'], 2) }} hr</p>
                                <p class="text-slate-600">{{ $line['concern'] }} · {{ $line['labor_description'] }}</p>
                                <p class="tabular-nums text-slate-500">{{ $line['recognized_date'] }} · rate {{ $line['flag_rate_dollars'] === null ? '—' : '$'.number_format((float) $line['flag_rate_dollars'], 2) }} · {{ $money($line['earnings_cents']) }}</p>
                            </div>
                        @empty
                            <p class="mt-1 text-xs text-slate-500">No recognized flag in this period.</p>
                        @endforelse
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Pending flag</p>
                        @forelse ($a['pending_lines'] as $line)
                            <div class="mt-1 border border-slate-100 px-2 py-1.5 text-xs">
                                <p class="font-semibold text-slate-900">RO {{ $line['repair_order_id'] }} · {{ $line['vehicle'] }} · {{ number_format((float) $line['flag_hours'], 2) }} hr</p>
                                <p class="text-slate-600">{{ $line['concern'] }} · {{ $line['labor_description'] }}</p>
                                <p class="text-slate-500">{{ $line['production_status_label'] ?? '—' }} · {{ $line['attribution_source'] }}</p>
                            </div>
                        @empty
                            <p class="mt-1 text-xs text-slate-500">No pending flag attributed to this technician.</p>
                        @endforelse
                    </div>

                    @if (count($a['unassigned_pending_lines'] ?? []) > 0)
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-amber-800">Unassigned pending (not counted to a technician)</p>
                            @foreach ($a['unassigned_pending_lines'] as $line)
                                <div class="mt-1 border border-amber-100 bg-amber-50/40 px-2 py-1.5 text-xs">
                                    <p class="font-semibold">RO {{ $line['repair_order_id'] }} · {{ $line['vehicle'] }} · {{ number_format((float) $line['flag_hours'], 2) }} hr</p>
                                    <p class="text-slate-600">{{ $line['labor_description'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endunless

                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Policy</p>
                    <p class="mt-1 text-xs text-slate-700">{{ $a['policy']['recognition_policy_label'] }}</p>
                    <p class="mt-1 text-xs text-slate-600">{{ $a['policy']['explanation'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $a['attribution_note'] ?? '' }}</p>
                </div>
            </div>
        </details>
    </section>
</x-operations.app>
