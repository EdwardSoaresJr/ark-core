<div class="space-y-3">
    @include('operations.reports.partials.payment-reconciliation')

    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Financial Mix</p>
            <p class="text-xs text-slate-400">Server-grounded sales, known costs, and GP posture</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Category</th>
                        <th class="px-3 py-2 text-right font-bold">Sales</th>
                        <th class="px-3 py-2 text-right font-bold">Cost</th>
                        <th class="px-3 py-2 text-right font-bold">GP</th>
                        <th class="px-3 py-2 text-right font-bold">Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($financialRows as $row)
                        <tr>
                            <td class="px-3 py-2 font-bold text-slate-950">{{ $row['category'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['sales'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ $row['cost'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-800">{{ $row['gp'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-600">{{ $row['margin'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Deferred Work Opportunity</p>
            <p class="text-xs text-slate-400">Future service inventory, not marketing campaigns</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Bucket</th>
                        <th class="px-3 py-2 text-right font-bold">Count</th>
                        <th class="px-3 py-2 text-right font-bold">Value</th>
                        <th class="px-3 py-2 font-bold">Operational Use</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($opportunityRows as $row)
                        <tr>
                            <td class="px-3 py-2 font-bold text-slate-950">{{ $row['bucket'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['count'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-700">{{ $row['value'] }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $row['next_action'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Recent Posts</p>
            <p class="text-xs text-slate-400">Posted sales in the selected range</p>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($recentClosures as $closure)
                <a href="{{ route('operations.repair-orders.show', $closure['repair_order_id']) }}" class="block px-3 py-2 text-sm hover:bg-slate-50">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-slate-950">#{{ $closure['repair_order_id'] }} · {{ $closure['customer'] }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $closure['vehicle'] }} · {{ $closure['closed_at']?->format('M j, g:i A') }}</p>
                        </div>
                        <p class="shrink-0 font-black tabular-nums text-slate-950">{{ $closure['total'] }}</p>
                    </div>
                </a>
            @empty
                <div class="px-3 py-6 text-center text-sm text-slate-500">
                    No posted repair orders in this range.
                </div>
            @endforelse
        </div>
    </div>
</div>
