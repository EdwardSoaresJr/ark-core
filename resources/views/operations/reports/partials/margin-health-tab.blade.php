<div class="space-y-3">
    <div class="overflow-hidden border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Margin Health</p>
            <p class="text-xs text-slate-400">
                Closed sales truth vs Demo Auto Repair targets.
                Edit bands in
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950">Settings → Owner Targets &amp; Reporting</a>.
                Parts matrix lives in
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'financial']) }}" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950">Financial Rules</a>.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-white text-left text-[10px] uppercase tracking-[0.08em] text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-bold">Metric</th>
                        <th class="px-3 py-2 text-right font-bold">Actual</th>
                        <th class="px-3 py-2 text-right font-bold">Target</th>
                        <th class="px-3 py-2 font-bold">Posture</th>
                        <th class="px-3 py-2 font-bold">Next action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($marginHealthRows as $row)
                        <tr @class([
                            'bg-emerald-50/40' => ($row['tone'] ?? null) === 'good',
                            'bg-amber-50/50' => ($row['tone'] ?? null) === 'warn',
                        ])>
                            <td class="px-3 py-2 font-bold text-slate-950">{{ $row['metric'] }}</td>
                            <td class="px-3 py-2 text-right font-black tabular-nums text-slate-950">{{ $row['actual'] }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-slate-500">{{ $row['target'] }}</td>
                            <td @class([
                                'px-3 py-2 text-xs font-semibold',
                                'text-emerald-800' => ($row['tone'] ?? null) === 'good',
                                'text-amber-800' => ($row['tone'] ?? null) === 'warn',
                                'text-slate-600' => ! in_array($row['tone'] ?? null, ['good', 'warn'], true),
                            ])>{{ $row['posture'] }}</td>
                            <td class="px-3 py-2 text-xs leading-4 text-slate-600">{{ $row['action'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($breakEvenSummary !== null)
        <div class="overflow-hidden border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Break-even pulse</p>
                <p class="text-xs text-slate-400">
                    Closed labor + parts GP vs prorated monthly fixed costs ({{ $breakEvenSummary['range_days'] }} days in range).
                    Monthly fixed: {{ $breakEvenSummary['monthly_fixed_label'] }}.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-px bg-slate-200 sm:grid-cols-4">
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Closed GP</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950">{{ $breakEvenSummary['gross_profit_label'] }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $breakEvenSummary['gross_margin_percent'] !== null ? $breakEvenSummary['gross_margin_percent'].'% of posted sales' : 'No posted sales' }}</p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Prorated fixed</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950">{{ $breakEvenSummary['prorated_fixed_label'] }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">Monthly pool spread across range</p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Surplus / gap</p>
                    <p @class([
                        'mt-1 text-lg font-black tabular-nums',
                        'text-emerald-800' => $breakEvenSummary['tone'] === 'good',
                        'text-amber-800' => $breakEvenSummary['tone'] === 'warn',
                    ])>{{ $breakEvenSummary['surplus_label'] }}</p>
                    <p @class([
                        'mt-0.5 text-[11px] font-medium',
                        'text-emerald-700' => $breakEvenSummary['tone'] === 'good',
                        'text-amber-700' => $breakEvenSummary['tone'] === 'warn',
                    ])>{{ $breakEvenSummary['posture'] }}</p>
                </div>
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Monthly break-even sales</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950">{{ $breakEvenSummary['monthly_break_even_sales_label'] ?? 'n/a' }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">At current closed GP margin</p>
                </div>
            </div>
            <p class="border-t border-slate-100 px-3 py-2 text-xs text-slate-500">{{ $breakEvenSummary['action'] }}</p>
        </div>
    @else
        <div class="border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-xs text-slate-600">
            Add <strong>monthly fixed costs</strong> under
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}" class="font-semibold text-slate-800 underline decoration-slate-300 hover:text-slate-950">Settings → Owner Targets &amp; Reporting</a>
            to see break-even vs closed gross profit. Net profit still lives in your bookkeeper&apos;s P&amp;L.
        </div>
    @endif

    <p class="text-xs text-slate-500">
        Weekly rhythm:
        <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'weekly-owner-review']) }}" class="font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-950">Weekly owner review</a>.
    </p>
</div>
