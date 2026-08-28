<div class="ops-report-layout">
    <div class="space-y-3">
        <div class="overflow-hidden border border-slate-300 bg-white">
            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Operational Pressure</p>
                    <p class="text-xs text-slate-400">What needs owner/operator attention now</p>
                </div>
                <a href="{{ route('operations.index') }}" class="text-xs font-bold text-slate-600 hover:text-slate-950">Open Queue</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                        <tr>
                            <th class="px-3 py-2 font-bold">Pressure</th>
                            <th class="px-3 py-2 text-right font-bold">Count</th>
                            <th class="px-3 py-2 font-bold">Value / Detail</th>
                            <th class="px-3 py-2 font-bold">Next Move</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($pressureRows as $row)
                            <tr>
                                <td class="px-3 py-2 font-bold text-slate-950">{{ $row['pressure'] }}</td>
                                <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['count'] }}</td>
                                <td class="max-w-xs truncate px-3 py-2 text-xs font-semibold text-slate-600">{{ $row['value'] }}</td>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $row['action'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <div class="overflow-hidden border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Advisor Throughput</p>
                <p class="text-xs text-slate-400">Shop-level until advisor ownership is explicit</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($advisorRows as $row)
                    <div class="grid grid-cols-2 gap-px bg-slate-100 text-sm">
                        <div class="bg-white px-3 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Owner</p>
                            <p class="font-black text-slate-950">{{ $row['advisor'] }}</p>
                        </div>
                        <div class="bg-white px-3 py-2 text-right">
                            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">ROs</p>
                            <p class="font-black tabular-nums text-slate-950">{{ $row['ro_count'] }}</p>
                        </div>
                        <div class="bg-white px-3 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Value</p>
                            <p class="font-bold tabular-nums text-slate-800">{{ $row['value'] }}</p>
                        </div>
                        <div class="bg-white px-3 py-2 text-right">
                            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Approvals</p>
                            <p class="font-bold tabular-nums text-slate-800">{{ $row['approvals'] }}</p>
                        </div>
                        <div class="bg-white px-3 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Deferred</p>
                            <p class="font-bold tabular-nums text-slate-800">{{ $row['deferred'] }}</p>
                        </div>
                        <div class="bg-white px-3 py-2 text-right">
                            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Unpaid</p>
                            <p class="font-bold tabular-nums text-slate-800">{{ $row['unpaid'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="overflow-hidden border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Technician Production</p>
                <p class="text-xs text-slate-400">Closed labor sold, queue load, and billed-hour efficiency vs shop open days (Communications hours)</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($technicianRows as $row)
                    <div class="px-3 py-2 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-black text-slate-950">{{ $row['technician'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $row['assigned'] }} assigned · {{ $row['active'] }} active · {{ $row['blockers'] }} blockers · {{ $row['hours'] }} hrs on board</p>
                                <p class="mt-0.5 text-xs font-semibold text-slate-600">{{ $row['efficiency'] }} efficiency · {{ $row['efficiency_hint'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-black tabular-nums text-slate-950">{{ $row['labor'] }}</p>
                                <p class="text-xs font-semibold tabular-nums text-slate-500">{{ $row['closed_hours'] }} hrs closed</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
