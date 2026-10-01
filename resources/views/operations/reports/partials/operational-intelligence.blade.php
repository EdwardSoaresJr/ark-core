<div class="space-y-3">
    @include('operations.reports.partials.shop-behavior-pulse')

    @include('operations.reports.partials.advisor-diagnostic-signals')

    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Operational Truth</p>
            <p class="text-xs text-slate-400">Where is work accumulating? Prior = already in bucket at period start.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Bucket</th>
                        <th class="px-3 py-2 text-right font-bold">Current</th>
                        <th class="px-3 py-2 text-right font-bold">Period Start</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($operationalTruth['buckets'] as $row)
                        <tr>
                            <td class="px-3 py-2 font-bold text-slate-950">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['current'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-600">{{ $row['prior_period'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($operationalTruth['drilldown']) > 0)
            <div class="border-t border-slate-200">
                <div class="border-b border-slate-100 bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Drill-down</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                            <tr>
                                <th class="px-3 py-2 font-bold">RO</th>
                                <th class="px-3 py-2 font-bold">Customer</th>
                                <th class="px-3 py-2 font-bold">Vehicle</th>
                                <th class="px-3 py-2 font-bold">Bucket</th>
                                <th class="px-3 py-2 font-bold">Status</th>
                                <th class="px-3 py-2 font-bold">Age</th>
                                <th class="px-3 py-2 font-bold">Last Activity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($operationalTruth['drilldown'] as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 font-bold text-slate-950">
                                        <a href="{{ $row['url'] }}" class="hover:underline">#{{ $row['repair_order_id'] }}</a>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ $row['customer'] }}</td>
                                    <td class="max-w-xs truncate px-3 py-2 text-slate-600">{{ $row['vehicle'] }}</td>
                                    <td class="px-3 py-2 text-xs font-semibold text-slate-500">{{ $row['bucket'] }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-500">{{ $row['status'] }}</td>
                                    <td class="px-3 py-2 font-bold tabular-nums text-slate-800">{{ $row['age'] }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-500">{{ $row['last_activity'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Approval Momentum</p>
            <p class="text-xs text-slate-400">Where are estimates dying? Funnel uses ROs opened in range; awaiting and aging are live queue.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Stage</th>
                        <th class="px-3 py-2 text-right font-bold">Count</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($approvalMomentum['stages'] as $row)
                        <tr>
                            <td class="px-3 py-2 font-bold text-slate-950">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($approvalMomentum['drilldown']) > 0)
            <div class="border-t border-slate-200">
                <div class="border-b border-slate-100 bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Drill-down</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                            <tr>
                                <th class="px-3 py-2 font-bold">RO</th>
                                <th class="px-3 py-2 font-bold">Customer</th>
                                <th class="px-3 py-2 font-bold">Sent</th>
                                <th class="px-3 py-2 font-bold">Viewed</th>
                                <th class="px-3 py-2 font-bold">Last Activity</th>
                                <th class="px-3 py-2 font-bold">Age</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($approvalMomentum['drilldown'] as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 font-bold text-slate-950">
                                        <a href="{{ $row['url'] }}" class="hover:underline">#{{ $row['repair_order_id'] }}</a>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ $row['customer'] }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-500">{{ $row['sent'] }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-500">{{ $row['viewed'] }}</td>
                                    <td class="px-3 py-2 text-xs text-slate-500">{{ $row['last_activity'] }}</td>
                                    <td class="px-3 py-2 font-bold tabular-nums text-slate-800">{{ $row['age'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Labor Liability</p>
            <p class="text-xs text-slate-400">Where did labor capacity go? Approved labor on ROs opened in range; value uses shop opportunity rate when sell is $0.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Category</th>
                        <th class="px-3 py-2 text-right font-bold">Hours</th>
                        <th class="px-3 py-2 text-right font-bold">Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($liability['rows'] as $row)
                        <tr>
                            <td class="px-3 py-2 font-bold text-slate-950">{{ $row['category'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['hours'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-800">{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-slate-50">
                        <td class="px-3 py-2 font-black text-slate-950">Total Non-Billable</td>
                        <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $liability['total_hours'] }}</td>
                        <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $liability['total_value'] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Recommendation Conversion</p>
            <p class="text-xs text-slate-400">What recommendations are converting? Concerns on ROs opened in range, grouped by recommendation intent.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Intent</th>
                        <th class="px-3 py-2 text-right font-bold">Recommended</th>
                        <th class="px-3 py-2 text-right font-bold">Approved</th>
                        <th class="px-3 py-2 text-right font-bold">Deferred</th>
                        <th class="px-3 py-2 text-right font-bold">Deferred $</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($recommendationConversion as $row)
                        <tr>
                            <td class="px-3 py-2 font-bold text-slate-950">{{ $row['intent'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['recommended'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-800">{{ $row['approved'] }}</td>
                            <td class="px-3 py-2 text-right font-bold tabular-nums text-slate-700">{{ $row['deferred'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['deferred_value'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
