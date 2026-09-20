@if (count($advisorQueuePressure ?? []) > 0)
    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Advisor Queue Pressure</p>
            <p class="text-xs text-slate-400">Live operational queue grouped by inferred service advisor — from intake creator or estimate owner.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Advisor</th>
                        <th class="px-3 py-2 text-right font-bold">Queue</th>
                        <th class="px-3 py-2 text-right font-bold">Approval</th>
                        <th class="px-3 py-2 text-right font-bold">Parts</th>
                        <th class="px-3 py-2 text-right font-bold">Unpaid</th>
                        <th class="px-3 py-2 font-bold">Pressure</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($advisorQueuePressure as $row)
                        <tr>
                            <td class="px-3 py-2 font-bold text-slate-950">{{ $row['advisor'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['queue'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-800">{{ $row['waiting_approval'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-700">{{ $row['parts'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-700">{{ $row['unpaid'] }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $row['hint'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="overflow-hidden border border-slate-300 bg-white">
    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Diagnostic → Repair Follow-Through</p>
        <p class="text-xs text-slate-400">{{ $diagnosticRepairFollowThrough['hint'] }}</p>
    </div>
    <div class="grid gap-px border-t border-slate-200 bg-slate-200 sm:grid-cols-3">
        <div class="bg-white px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Diagnostic ROs</p>
            <p class="mt-0.5 text-lg font-black tabular-nums text-slate-950">{{ $diagnosticRepairFollowThrough['diagnostic_ros'] }}</p>
        </div>
        <div class="bg-white px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Repair approved</p>
            <p class="mt-0.5 text-lg font-black tabular-nums text-slate-950">{{ $diagnosticRepairFollowThrough['repair_follow_through'] }}</p>
        </div>
        <div class="bg-white px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Follow-through rate</p>
            <p class="mt-0.5 text-lg font-black tabular-nums text-slate-950">{{ $diagnosticRepairFollowThrough['rate_label'] }}</p>
        </div>
    </div>
</div>
