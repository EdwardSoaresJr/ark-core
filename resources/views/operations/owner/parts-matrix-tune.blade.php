<x-operations.app title="Parts Matrix Tune" ui="tabler">
    @php
        $posture = $analysis['posture'];
        $simulation = $analysis['simulation'];
        $recommendation = $analysis['recommendation'];
        $toneClass = fn (?string $tone): string => match ($tone) {
            'good' => 'text-success',
            'warn' => 'text-orange',
            default => 'text-secondary',
        };
    @endphp

    <x-tabler.page layout="fluid" title="Simulate parts matrix changes from closed truth" pretitle="Matrix tune assistant">
        <div class="card mb-3" data-matrix-tune-sample>
            <div class="card-header">
                <div>
                    <h3 class="card-title">Closed sample</h3>
                    <div class="card-subtitle">
                        Matrix sets price. Review a closed sample before changing live policy.
                        Simulation never writes to shop settings; apply manually after review.
                    </div>
                </div>
            </div>

            <form method="GET" action="{{ route('operations.owner.parts-matrix-tune') }}" class="card-body border-bottom">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-auto">
                        <label class="form-label" for="matrix-tune-from">From</label>
                        <input id="matrix-tune-from" type="date" name="from" value="{{ $analysis['from'] }}" min="{{ $analysis['trustworthy_floor'] }}" class="form-control">
                    </div>
                    <div class="col-6 col-md-auto">
                        <label class="form-label" for="matrix-tune-to">To</label>
                        <input id="matrix-tune-to" type="date" name="to" value="{{ $analysis['to'] }}" class="form-control">
                    </div>
                    <div class="col-12 col-md-auto">
                        <label class="form-label" for="matrix-tune-matrix">Matrix</label>
                        <select id="matrix-tune-matrix" name="matrix" class="form-select">
                            @foreach ($matrices as $matrix)
                                <option value="{{ $matrix['key'] }}" @selected($matrix['key'] === $analysis['matrix_key'])>{{ $matrix['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn w-100">Refresh sample</button>
                    </div>
                </div>
            </form>

            <div class="card-body border-bottom">
                <div class="alert alert-warning mb-0" role="status" data-matrix-tune-recommendation>
                    <h4 class="alert-title">{{ $recommendation['headline'] }}</h4>
                    <div class="text-secondary">{{ $recommendation['detail'] }}</div>
                    <div class="fw-medium mt-1">{{ $recommendation['action'] }}</div>
                </div>
            </div>

            @if ($analysis['insufficient_data'])
                <div class="card-body border-bottom text-secondary small">
                    <p class="mb-1">{{ $analysis['sample_count'] }} closed part lines sampled (need {{ $analysis['minimum_sample_lines'] }} minimum).</p>
                    <p class="mb-0">Trustworthy data starts {{ $analysis['trustworthy_floor'] }}. Tool structure is ready - analysis fills in as ROs close.</p>
                </div>
            @endif

            <div class="card-body">
                <div class="row g-3" data-matrix-tune-posture>
                    @foreach (['actual' => $posture['actual'], 'matrix_discipline' => $posture['matrix_discipline'], 'simulated' => $posture['simulated']] as $key => $row)
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="subheader">{{ $row['label'] }}</div>
                            <div class="h2 mb-0 ark-money">
                                {{ $row['margin_percent'] !== null ? $row['margin_percent'].'%' : 'n/a' }}
                            </div>
                            <div class="small {{ $toneClass($row['tone'] ?? null) }}">
                                target {{ $posture['target_margin_percent'] }}% · {{ $money($row['gp_cents']) }} GP on {{ $money($row['sales_cents']) }} sales
                            </div>
                        </div>
                    @endforeach
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="subheader">Parts / labor mix (closed)</div>
                        <div class="h2 mb-0 ark-money">
                            @if ($posture['mix']['parts_percent'] !== null)
                                {{ $posture['mix']['parts_percent'] }}% / {{ $posture['mix']['labor_percent'] }}%
                            @else
                                n/a
                            @endif
                        </div>
                        <div class="small {{ $toneClass($posture['mix']['tone'] ?? null) }}">
                            target {{ $posture['mix']['target_parts_percent'] }}/{{ $posture['mix']['target_labor_percent'] }} · matrix does not fix mix
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer text-secondary small">
                {{ $analysis['range_label'] }} · {{ $analysis['matrix_name'] }} · {{ $analysis['sample_count'] }} part lines ·
                {{ $analysis['discipline']['override_lines'] }} overrides · {{ $analysis['discipline']['matrix_lines'] }} matrix-following
            </div>
        </div>

        <form method="GET" action="{{ route('operations.owner.parts-matrix-tune') }}" class="card mb-3" data-matrix-tune-simulation>
            <input type="hidden" name="from" value="{{ $analysis['from'] }}">
            <input type="hidden" name="to" value="{{ $analysis['to'] }}">
            <input type="hidden" name="matrix" value="{{ $analysis['matrix_key'] }}">
            <input type="hidden" name="simulate" value="1">

            <div class="card-header">
                <div>
                    <h3 class="card-title">Tier simulation</h3>
                    <div class="card-subtitle">Adjust proposed markup % per cost band. Overrides stay at actual sell; non-overridden lines repriced through {{ $analysis['matrix_name'] }} tiers.</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table card-table table-vcenter">
                    <thead>
                        <tr>
                            <th>Cost band</th>
                            <th>Sample</th>
                            <th>Actual margin</th>
                            <th>Current markup</th>
                            <th>Proposed markup</th>
                            <th>Simulated margin</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($analysis['tiers'] as $tier)
                            @php
                                $costBand = '$'.$tier['min_cost'].($tier['max_cost'] ? ' – $'.$tier['max_cost'] : '+');
                            @endphp
                            <tr>
                                <td class="ark-money text-nowrap">{{ $costBand }}</td>
                                <td class="ark-money text-secondary text-nowrap">
                                    {{ $tier['sample_lines'] }} lines
                                    @if ($tier['override_lines'] > 0)
                                        <span class="text-orange">· {{ $tier['override_lines'] }} override</span>
                                    @endif
                                </td>
                                <td class="ark-money fw-bold">
                                    {{ $tier['actual_margin_percent'] !== null ? $tier['actual_margin_percent'].'%' : '-' }}
                                </td>
                                <td class="ark-money text-secondary text-nowrap">
                                    {{ $tier['current_markup'] }}%
                                    <span class="text-secondary">({{ $tier['current_margin_percent'] ?? '-' }}% margin)</span>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm flex-nowrap" style="width: 8rem">
                                        <input
                                            type="text"
                                            name="proposed_markup[{{ $tier['row_index'] }}]"
                                            value="{{ $tier['proposed_markup'] }}"
                                            inputmode="decimal"
                                            class="form-control ark-money"
                                            aria-label="Proposed markup for {{ $costBand }}"
                                        >
                                        <span class="input-group-text">%</span>
                                    </div>
                                </td>
                                <td class="ark-money fw-bold">
                                    {{ $tier['simulated_margin_percent'] !== null ? $tier['simulated_margin_percent'].'%' : '-' }}
                                </td>
                                <td class="text-secondary small">{{ $tier['recommendation'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md text-secondary small" data-matrix-tune-delta>
                        @if ($simulation['delta_margin_points'] !== null)
                            Simulation delta:
                            <span @class(['fw-bold ark-money', 'text-success' => $simulation['delta_margin_points'] > 0])>
                                {{ $simulation['delta_margin_points'] >= 0 ? '+' : '' }}{{ $simulation['delta_margin_points'] }} pts
                            </span>
                            · projected +{{ $money($simulation['additional_gp_cents']) }} GP on sample
                            @if ($simulation['meets_target'])
                                <span class="fw-bold text-success">· meets target</span>
                            @endif
                        @else
                            Run simulation after sample data accumulates.
                        @endif
                    </div>
                    <div class="col-12 col-md-auto">
                        <button type="submit" class="btn btn-primary w-100">Simulate proposed markups</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="btn-list" data-matrix-tune-links>
            <a href="{{ route('operations.owner.day-review') }}" class="btn">Day Review</a>
            <a href="{{ route('operations.reports.operational', ['tab' => 'margin-health', 'from' => $analysis['from'], 'to' => $analysis['to']]) }}" class="btn">Margin Health</a>
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'financial', 'financialTab' => 'parts']) }}" class="btn">Parts Matrix settings</a>
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}" class="btn">Owner Targets</a>
            <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'quarterly-target-review']) }}" class="btn">Quarterly review guide</a>
        </div>
    </x-tabler.page>
</x-operations.app>
