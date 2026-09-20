@php
    $summary = $ownerPlSummary;
@endphp

<div class="space-y-3">
    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Owner P&amp;L</p>
            <p class="text-xs text-slate-400">
                {{ $summary['posted_count'] }} posted RO{{ $summary['posted_count'] === 1 ? '' : 's' }} · {{ $summary['range_days'] }} days in range.
                {{ $summary['disclaimer'] }}
                Edit assumptions in
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950">Owner Targets &amp; Reporting</a>
                and
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'overhead']) }}" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950">Shop Overhead</a>.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Line</th>
                        <th class="px-3 py-2 text-right font-bold">Amount</th>
                        <th class="px-3 py-2 text-right font-bold">% rev</th>
                        <th class="px-3 py-2 font-bold">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($summary['pl_lines'] as $row)
                        <tr @class([
                            'bg-slate-50/80' => ($row['emphasis'] ?? 'normal') === 'subtotal',
                            'bg-slate-100/80' => ($row['emphasis'] ?? 'normal') === 'total',
                            'bg-emerald-50/40' => ($row['tone'] ?? null) === 'good',
                            'bg-amber-50/50' => ($row['tone'] ?? null) === 'warn',
                        ])>
                            <td @class([
                                'px-3 py-2 font-bold text-slate-950',
                                'pl-6 font-medium text-slate-700' => $row['indent'],
                                'text-base' => ($row['emphasis'] ?? 'normal') === 'total',
                            ])>{{ $row['label'] }}</td>
                            <td @class([
                                'px-3 py-2 text-right tabular-nums text-slate-950',
                                'font-black' => in_array($row['emphasis'] ?? 'normal', ['subtotal', 'total'], true),
                                'text-emerald-800' => ($row['tone'] ?? null) === 'good',
                                'text-amber-800' => ($row['tone'] ?? null) === 'warn',
                            ])>{{ $row['amount'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ $row['percent'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs leading-4 text-slate-500">{{ $row['note'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($summary['benchmark'] !== null)
        <div class="overflow-hidden border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Net profit benchmark</p>
                <p class="text-xs text-slate-400">
                    {{ $summary['benchmark']['net_target_percent'] }}% net on service revenue — after prorated fixed costs and estimated tax reserves.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-px bg-slate-200 sm:grid-cols-4">
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $summary['benchmark']['net_target_percent'] }}% target</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950">{{ $summary['benchmark']['net_target_label'] }}</p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Est. net after reserves</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950">{{ $summary['benchmark']['estimated_net_label'] ?? 'Configure fixed costs' }}</p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Gap to target</p>
                    <p @class([
                        'mt-1 text-lg font-black tabular-nums',
                        'text-emerald-800' => ($summary['benchmark']['tone'] ?? null) === 'good',
                        'text-amber-800' => ($summary['benchmark']['tone'] ?? null) === 'warn',
                        'text-slate-950' => ! in_array($summary['benchmark']['tone'] ?? null, ['good', 'warn'], true),
                    ])>{{ $summary['benchmark']['gap_label'] ?? '—' }}</p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Posture</p>
                    <p @class([
                        'mt-1 text-xs font-semibold leading-4',
                        'text-emerald-800' => ($summary['benchmark']['tone'] ?? null) === 'good',
                        'text-amber-800' => ($summary['benchmark']['tone'] ?? null) === 'warn',
                        'text-slate-600' => ! in_array($summary['benchmark']['tone'] ?? null, ['good', 'warn'], true),
                    ])>{{ $summary['benchmark']['posture'] }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Tax &amp; remittance posture</p>
            <p class="text-xs text-slate-400">Sales tax is from posted RO lines. Payroll and income lines are planning reserves — not filing amounts.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Item</th>
                        <th class="px-3 py-2 text-right font-bold">Amount</th>
                        <th class="px-3 py-2 font-bold">Source</th>
                        <th class="px-3 py-2 font-bold">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($summary['tax_lines'] as $row)
                        <tr @class([
                            'bg-slate-50/80' => str_starts_with($row['label'], 'Total tax'),
                        ])>
                            <td class="px-3 py-2 font-bold text-slate-950">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['amount'] }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ $row['source'] }}</td>
                            <td class="px-3 py-2 text-xs leading-4 text-slate-500">{{ $row['note'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($breakEvenSummary !== null)
        <div class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
            <span class="font-semibold text-slate-800">Break-even cross-check:</span>
            posted GP {{ $breakEvenSummary['gross_profit_label'] }} vs prorated fixed {{ $breakEvenSummary['prorated_fixed_label'] }}
            ({{ $breakEvenSummary['surplus_label'] }}).
            <a href="{{ route('operations.reports.operational', ['from' => \App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($from), 'to' => \App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($to), 'tab' => 'margin-health']) }}" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950">Margin Health</a>
            has the full margin rows.
        </div>
    @endif
</div>
