<x-operations.app title="Parts Matrix Tune">
    @php
        $posture = $analysis['posture'];
        $simulation = $analysis['simulation'];
        $recommendation = $analysis['recommendation'];
    @endphp

    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Matrix tune assistant</p>
                <h1 class="mt-0.5 text-base font-black text-slate-950">Simulate parts matrix changes from closed truth</h1>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Matrix sets price. Review a closed sample before changing live policy.
                    Simulation never writes to shop settings; apply manually after review.
                </p>
            </div>

            <form method="GET" action="{{ route('operations.owner.parts-matrix-tune') }}" class="border-b border-slate-200 bg-white px-3 py-3">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="block text-xs font-semibold text-slate-600">
                        From
                        <input type="date" name="from" value="{{ $analysis['from'] }}" min="{{ $analysis['trustworthy_floor'] }}" class="mt-1 block rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                    </label>
                    <label class="block text-xs font-semibold text-slate-600">
                        To
                        <input type="date" name="to" value="{{ $analysis['to'] }}" class="mt-1 block rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                    </label>
                    <label class="block text-xs font-semibold text-slate-600">
                        Matrix
                        <select name="matrix" class="mt-1 block min-w-[12rem] rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                            @foreach ($matrices as $matrix)
                                <option value="{{ $matrix['key'] }}" @selected($matrix['key'] === $analysis['matrix_key'])>{{ $matrix['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="rounded-sm border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-800 hover:border-slate-400">Refresh sample</button>
                </div>
            </form>

            <div class="border-b border-amber-100 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                <p class="font-bold">{{ $recommendation['headline'] }}</p>
                <p class="mt-0.5">{{ $recommendation['detail'] }}</p>
                <p class="mt-1 font-medium">{{ $recommendation['action'] }}</p>
            </div>

            @if ($analysis['insufficient_data'])
                <div class="px-3 py-3 text-xs text-slate-600">
                    <p>{{ $analysis['sample_count'] }} closed part lines sampled (need {{ $analysis['minimum_sample_lines'] }} minimum).</p>
                    <p class="mt-1 text-slate-500">Trustworthy data starts {{ $analysis['trustworthy_floor'] }}. Tool structure is ready — analysis fills in as ROs close.</p>
                </div>
            @endif

            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['actual' => $posture['actual'], 'matrix_discipline' => $posture['matrix_discipline'], 'simulated' => $posture['simulated']] as $key => $row)
                    <div class="bg-white px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $row['label'] }}</p>
                        <p class="mt-1 text-lg font-black tabular-nums text-slate-950">
                            {{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : 'n/a' }}
                        </p>
                        <p @class([
                            'mt-0.5 text-[11px] font-medium',
                            'text-emerald-700' => ($row['tone'] ?? null) === 'good',
                            'text-amber-700' => ($row['tone'] ?? null) === 'warn',
                            'text-slate-400' => ! in_array($row['tone'] ?? null, ['good', 'warn'], true),
                        ])>
                            target {{ $posture['target_margin_percent'] }}% · {{ $money($row['gp_cents']) }} GP on {{ $money($row['sales_cents']) }} sales
                        </p>
                    </div>
                @endforeach
                <div class="bg-white px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Parts / labor mix (closed)</p>
                    <p class="mt-1 text-lg font-black tabular-nums text-slate-950">
                        @if ($posture['mix']['parts_percent'] !== null)
                            {{ $posture['mix']['parts_percent'] }}% / {{ $posture['mix']['labor_percent'] }}%
                        @else
                            n/a
                        @endif
                    </p>
                    <p @class([
                        'mt-0.5 text-[11px] font-medium',
                        'text-emerald-700' => ($posture['mix']['tone'] ?? null) === 'good',
                        'text-amber-700' => ($posture['mix']['tone'] ?? null) === 'warn',
                        'text-slate-400' => ! in_array($posture['mix']['tone'] ?? null, ['good', 'warn'], true),
                    ])>
                        target {{ $posture['mix']['target_parts_percent'] }}/{{ $posture['mix']['target_labor_percent'] }} · matrix does not fix mix
                    </p>
                </div>
            </div>

            <div class="border-t border-slate-200 px-3 py-2 text-xs text-slate-500">
                {{ $analysis['range_label'] }} · {{ $analysis['matrix_name'] }} · {{ $analysis['sample_count'] }} part lines ·
                {{ $analysis['discipline']['override_lines'] }} overrides · {{ $analysis['discipline']['matrix_lines'] }} matrix-following
            </div>
        </div>

        <form method="GET" action="{{ route('operations.owner.parts-matrix-tune') }}" class="border border-slate-300 bg-white">
            <input type="hidden" name="from" value="{{ $analysis['from'] }}">
            <input type="hidden" name="to" value="{{ $analysis['to'] }}">
            <input type="hidden" name="matrix" value="{{ $analysis['matrix_key'] }}">
            <input type="hidden" name="simulate" value="1">

            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Tier simulation</p>
                <p class="text-xs text-slate-500">Adjust proposed markup % per cost band. Overrides stay at actual sell; non-overridden lines repriced through {{ $analysis['matrix_name'] }} tiers.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="border-b border-slate-200 px-3 py-2 text-left">Cost band</th>
                            <th class="border-b border-slate-200 px-3 py-2 text-left">Sample</th>
                            <th class="border-b border-slate-200 px-3 py-2 text-left">Actual margin</th>
                            <th class="border-b border-slate-200 px-3 py-2 text-left">Current markup</th>
                            <th class="border-b border-slate-200 px-3 py-2 text-left">Proposed markup</th>
                            <th class="border-b border-slate-200 px-3 py-2 text-left">Simulated margin</th>
                            <th class="border-b border-slate-200 px-3 py-2 text-left">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($analysis['tiers'] as $tier)
                            <tr>
                                <td class="px-3 py-2 align-middle tabular-nums text-slate-700">
                                    ${{ $tier['min_cost'] }}@if ($tier['max_cost']) – ${{ $tier['max_cost'] }}@else+@endif
                                </td>
                                <td class="px-3 py-2 align-middle tabular-nums text-slate-600">
                                    {{ $tier['sample_lines'] }} lines
                                    @if ($tier['override_lines'] > 0)
                                        <span class="text-amber-700">· {{ $tier['override_lines'] }} override</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 align-middle tabular-nums font-semibold text-slate-950">
                                    {{ $tier['actual_margin_percent'] !== null ? $tier['actual_margin_percent'].'%' : '—' }}
                                </td>
                                <td class="px-3 py-2 align-middle tabular-nums text-slate-600">
                                    {{ $tier['current_markup'] }}%
                                    <span class="text-slate-400">({{ $tier['current_margin_percent'] ?? '—' }}% margin)</span>
                                </td>
                                <td class="px-3 py-2 align-middle">
                                    <input
                                        type="text"
                                        name="proposed_markup[{{ $tier['row_index'] }}]"
                                        value="{{ $tier['proposed_markup'] }}"
                                        inputmode="decimal"
                                        class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-sm tabular-nums text-slate-950"
                                    >
                                    <span class="text-slate-400">%</span>
                                </td>
                                <td class="px-3 py-2 align-middle tabular-nums font-semibold text-slate-950">
                                    {{ $tier['simulated_margin_percent'] !== null ? $tier['simulated_margin_percent'].'%' : '—' }}
                                </td>
                                <td class="px-3 py-2 align-middle text-xs text-slate-500">{{ $tier['recommendation'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-3 py-3">
                <div class="text-xs text-slate-600">
                    @if ($simulation['delta_margin_points'] !== null)
                        Simulation delta:
                        <span @class(['font-bold tabular-nums', 'text-emerald-700' => $simulation['delta_margin_points'] > 0, 'text-slate-950' => $simulation['delta_margin_points'] <= 0])>
                            {{ $simulation['delta_margin_points'] >= 0 ? '+' : '' }}{{ $simulation['delta_margin_points'] }} pts
                        </span>
                        · projected +{{ $money($simulation['additional_gp_cents']) }} GP on sample
                        @if ($simulation['meets_target'])
                            <span class="font-bold text-emerald-700">· meets target</span>
                        @endif
                    @else
                        Run simulation after sample data accumulates.
                    @endif
                </div>
                <button type="submit" class="rounded-sm bg-slate-950 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800">Simulate proposed markups</button>
            </div>
        </form>

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('operations.owner.day-review') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Day Review</a>
            <a href="{{ route('operations.reports.operational', ['tab' => 'margin-health', 'from' => $analysis['from'], 'to' => $analysis['to']]) }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Margin Health</a>
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'financial', 'financialTab' => 'parts']) }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Parts Matrix settings</a>
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Owner Targets</a>
            <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'quarterly-target-review']) }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Quarterly review guide</a>
        </div>
    </section>
</x-operations.app>
