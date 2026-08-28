<x-operations.app title="Technician production">
    <section class="mx-auto max-w-6xl space-y-4 px-3 py-4 sm:px-4">
        <div class="border-b border-slate-200 pb-3">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Owner · Production assist</p>
            <h1 class="text-xl font-black text-slate-950">Technician production</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">{{ $northStar }}</p>
            <p class="mt-1 text-xs text-slate-500">Base compensation assist is not a paycheck. Overtime and payroll adjustments are not included. Recognition history begins {{ $recognitionStartsAt }}.</p>
        </div>

        <form method="GET" action="{{ route('operations.owner.technician-production.index') }}" class="flex flex-wrap items-end gap-3 border border-slate-200 bg-slate-50 px-3 py-3">
            <label class="block text-xs font-semibold text-slate-700">
                From
                <x-operations.date-field name="from" :value="$from" class="mt-1" />
            </label>
            <label class="block text-xs font-semibold text-slate-700">
                To
                <x-operations.date-field name="to" :value="$to" class="mt-1" />
            </label>
            <button type="submit" class="ops-index-btn ops-index-btn--primary">Show week</button>
        </form>

        <div class="overflow-x-auto border border-slate-300 bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-[0.06em] text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Technician</th>
                        <th class="px-3 py-2 text-right">Clock</th>
                        <th class="px-3 py-2 text-right">Recognized</th>
                        <th class="px-3 py-2 text-right">Pending</th>
                        <th class="px-3 py-2 text-right">Rec. / clock</th>
                        <th class="px-3 py-2 text-right">Floor exposure</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr @class(['bg-amber-50/60' => $row['overtime_review_required'] || $row['history_unavailable']])>
                            <td class="px-3 py-2.5 font-semibold text-slate-950">
                                {{ $row['technician_name'] }}
                                @if ($row['overtime_review_required'])
                                    <span class="ml-1 text-[10px] font-bold uppercase tracking-wide text-amber-800">OT review</span>
                                @endif
                                @if ($row['history_unavailable'])
                                    <span class="ml-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">History N/A</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ number_format((float) $row['clock_hours'], 1) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                @if ($row['history_unavailable'])
                                    —
                                @else
                                    {{ number_format((float) $row['recognized_flag_hours'], 1) }}
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                @if ($row['history_unavailable'])
                                    —
                                @else
                                    {{ number_format((float) $row['pending_flag_hours'], 1) }}
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums">
                                @if ($row['recognized_efficiency_percent'] === null)
                                    —
                                @else
                                    {{ number_format((float) $row['recognized_efficiency_percent'], 1) }}%
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-semibold">
                                @if ($row['history_unavailable'] || $row['floor_exposure_cents'] === null)
                                    —
                                @else
                                    ${{ number_format($row['floor_exposure_cents'] / 100, 2) }}
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <a href="{{ route('operations.owner.technician-production.show', ['user' => $row['technician_id'], 'from' => $from, 'to' => $to]) }}" class="text-xs font-bold text-slate-800 underline">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-sm text-slate-500">No Flag technicians for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-operations.app>
