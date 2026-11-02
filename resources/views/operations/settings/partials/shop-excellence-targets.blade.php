@php
    $excellenceField = fn (string $name): array => ['form-control', 'is-invalid' => $errors->has($name)];
@endphp

<div class="card-body">
    <div class="border rounded px-3 py-2 small" style="max-width: 48rem" data-excellence-posted-sales>
        <div class="subheader mb-1">Posted sales</div>
        <p class="text-secondary mb-0">
            <strong class="text-body">Posted invoice sales</strong> uses the frozen invoice on repair orders posted in the range, before tax.
            <strong class="text-body">Cash Collected</strong> uses ledger payment/deposit dates (Payment Details / Total Cashiered).
            Deposits and partial payments never inflate posted sales until the RO is posted.
            Close - Paid posts automatically; paid ROs can also be posted from the repair order when ready.
        </p>
    </div>
</div>

<form method="POST" action="{{ route('operations.settings.shop.excellence.update') }}" data-settings-excellence>
    @csrf
    @method('PATCH')

    <div class="card-body">
        <div style="max-width: 48rem">
            <h3 class="mb-1">Margin targets</h3>
            <p class="text-secondary small mb-1">ELR floor, ARO, parts margin, sales mix, and net profit targets.</p>
            <p class="text-secondary small">Posted labor rate comes from <a href="{{ route('operations.settings.shop.edit', ['section' => 'financial', 'financial-tab' => 'labor']) }}" @click.prevent="setActive('financial'); setFinancialTab('labor')">Financial Rules → Labor</a>.</p>

            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <div class="d-flex align-items-center gap-1 mb-2">
                        <label for="excellence-elr-floor" class="form-label mb-0">ELR floor</label>
                        <x-tabler.help-tip
                            text="Minimum acceptable effective labor rate on posted work."
                            label="ELR floor help"
                        />
                    </div>
                    <div @class(['input-group', 'has-validation' => $errors->has('effective_labor_rate_floor')])>
                        <span class="input-group-text">$</span>
                        <input
                            id="excellence-elr-floor"
                            type="number"
                            name="effective_labor_rate_floor"
                            min="0"
                            step="0.01"
                            value="{{ old('effective_labor_rate_floor', $excellenceTargets['effective_labor_rate_floor_cents'] !== null ? number_format($excellenceTargets['effective_labor_rate_floor_cents'] / 100, 2, '.', '') : '') }}"
                            placeholder="{{ $excellenceTargets['posted_labor_rate_cents'] !== null ? number_format($excellenceTargets['posted_labor_rate_cents'] / 100, 2) : '165.00' }}"
                            @class($excellenceField('effective_labor_rate_floor'))
                        >
                        <span class="input-group-text">/hr</span>
                        @error('effective_labor_rate_floor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-12 col-sm-6">
                    <label for="excellence-aro-target" class="form-label required">ARO target</label>
                    <div @class(['input-group', 'has-validation' => $errors->has('aro_target')])>
                        <span class="input-group-text">$</span>
                        <input
                            id="excellence-aro-target"
                            type="number"
                            name="aro_target"
                            min="0"
                            step="0.01"
                            required
                            value="{{ old('aro_target', number_format($excellenceTargets['aro_target_cents'] / 100, 2, '.', '')) }}"
                            @class($excellenceField('aro_target'))
                        >
                        @error('aro_target')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-12 col-sm-6">
                    <label for="excellence-parts-margin" class="form-label required">Parts margin target</label>
                    <div @class(['input-group', 'has-validation' => $errors->has('parts_margin_target_percent')])>
                        <input
                            id="excellence-parts-margin"
                            type="number"
                            name="parts_margin_target_percent"
                            min="1"
                            max="99"
                            required
                            value="{{ old('parts_margin_target_percent', $excellenceTargets['parts_margin_target_percent']) }}"
                            @class($excellenceField('parts_margin_target_percent'))
                        >
                        <span class="input-group-text">%</span>
                        @error('parts_margin_target_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                @if (($overheadWorksheetMonthlyFixedCostsCents ?? null) !== null)
                    <div class="col-12 col-sm-6" data-excellence-fixed-costs-from-overhead>
                        <div class="form-label">Monthly fixed costs ($)</div>
                        <div class="form-control-plaintext font-monospace py-0">{{ number_format($overheadWorksheetMonthlyFixedCostsCents / 100, 2) }}</div>
                        <div class="form-hint">Break-even on Margin Health. Comes from the fixed costs in the <a href="{{ route('operations.settings.shop.edit', ['section' => 'overhead']) }}" @click.prevent="setActive('overhead')">Shop Overhead worksheet</a>. Change it there.</div>
                    </div>
                @else
                    <div class="col-12 col-sm-6">
                        <label for="excellence-monthly-fixed-costs" class="form-label">Monthly fixed costs ($)</label>
                        <div @class(['input-group', 'has-validation' => $errors->has('monthly_fixed_costs')])>
                            <span class="input-group-text">$</span>
                            <input
                                id="excellence-monthly-fixed-costs"
                                type="number"
                                name="monthly_fixed_costs"
                                min="0"
                                step="0.01"
                                value="{{ old('monthly_fixed_costs', $excellenceTargets['monthly_fixed_costs_cents'] !== null ? number_format($excellenceTargets['monthly_fixed_costs_cents'] / 100, 2, '.', '') : '') }}"
                                placeholder="25000.00"
                                @class($excellenceField('monthly_fixed_costs'))
                            >
                            @error('monthly_fixed_costs')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-hint">Break-even on Margin Health. Once you save fixed costs in the <a href="{{ route('operations.settings.shop.edit', ['section' => 'overhead']) }}" @click.prevent="setActive('overhead')">Shop Overhead worksheet</a>, its monthly total is used here instead.</div>
                    </div>
                @endif

                <div class="col-12 col-sm-6">
                    <label for="excellence-labor-sales-mix" class="form-label required">Labor sales mix target</label>
                    <div @class(['input-group', 'has-validation' => $errors->has('labor_sales_target_percent')])>
                        <input
                            id="excellence-labor-sales-mix"
                            type="number"
                            name="labor_sales_target_percent"
                            min="1"
                            max="99"
                            required
                            value="{{ old('labor_sales_target_percent', $excellenceTargets['labor_sales_target_percent']) }}"
                            @class($excellenceField('labor_sales_target_percent'))
                        >
                        <span class="input-group-text">%</span>
                        @error('labor_sales_target_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-12 col-sm-6">
                    <label for="excellence-parts-sales-mix" class="form-label required">Parts sales mix target</label>
                    <div @class(['input-group', 'has-validation' => $errors->has('parts_sales_target_percent')])>
                        <input
                            id="excellence-parts-sales-mix"
                            type="number"
                            name="parts_sales_target_percent"
                            min="1"
                            max="99"
                            required
                            value="{{ old('parts_sales_target_percent', $excellenceTargets['parts_sales_target_percent']) }}"
                            @class($excellenceField('parts_sales_target_percent'))
                        >
                        <span class="input-group-text">%</span>
                        @error('parts_sales_target_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <h4 class="mt-4 mb-2">P&amp;L &amp; tax estimates</h4>
            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <div class="d-flex align-items-center gap-1 mb-2">
                        <label for="excellence-net-profit" class="form-label required mb-0">Net profit target</label>
                        <x-tabler.help-tip
                            text="~20% net on service revenue benchmark."
                            label="Net profit target help"
                        />
                    </div>
                    <div @class(['input-group', 'has-validation' => $errors->has('net_profit_target_percent')])>
                        <input
                            id="excellence-net-profit"
                            type="number"
                            name="net_profit_target_percent"
                            min="1"
                            max="99"
                            required
                            value="{{ old('net_profit_target_percent', $excellenceTargets['net_profit_target_percent']) }}"
                            @class($excellenceField('net_profit_target_percent'))
                        >
                        <span class="input-group-text">%</span>
                        @error('net_profit_target_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-12 col-sm-6">
                    <label for="excellence-income-tax-reserve" class="form-label required">Income tax reserve</label>
                    <div @class(['input-group', 'has-validation' => $errors->has('income_tax_reserve_percent')])>
                        <input
                            id="excellence-income-tax-reserve"
                            type="number"
                            name="income_tax_reserve_percent"
                            min="0"
                            max="99"
                            required
                            value="{{ old('income_tax_reserve_percent', $excellenceTargets['income_tax_reserve_percent']) }}"
                            @class($excellenceField('income_tax_reserve_percent'))
                        >
                        <span class="input-group-text">%</span>
                        @error('income_tax_reserve_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="col-12 col-sm-6">
                    <label for="excellence-payroll-tax-reserve" class="form-label required">Payroll tax reserve</label>
                    <div @class(['input-group', 'has-validation' => $errors->has('payroll_tax_reserve_percent')])>
                        <input
                            id="excellence-payroll-tax-reserve"
                            type="number"
                            name="payroll_tax_reserve_percent"
                            min="0"
                            max="99"
                            required
                            value="{{ old('payroll_tax_reserve_percent', $excellenceTargets['payroll_tax_reserve_percent']) }}"
                            @class($excellenceField('payroll_tax_reserve_percent'))
                        >
                        <span class="input-group-text">%</span>
                        @error('payroll_tax_reserve_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-hint">Percent of technician labor cost. Not used when Monthly payroll tax is above $0.</div>
                </div>

                <div class="col-12 col-sm-6">
                    <label for="excellence-monthly-payroll-tax" class="form-label">Monthly payroll tax</label>
                    <div @class(['input-group', 'has-validation' => $errors->has('monthly_payroll_tax')])>
                        <span class="input-group-text">$</span>
                        <input
                            id="excellence-monthly-payroll-tax"
                            type="number"
                            name="monthly_payroll_tax"
                            min="0"
                            step="0.01"
                            value="{{ old('monthly_payroll_tax', ($excellenceTargets['monthly_payroll_tax_cents'] ?? null) !== null ? number_format($excellenceTargets['monthly_payroll_tax_cents'] / 100, 2, '.', '') : '') }}"
                            placeholder="Optional"
                            @class($excellenceField('monthly_payroll_tax'))
                        >
                        @error('monthly_payroll_tax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-hint">When above $0, used instead of the payroll tax reserve %.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div style="max-width: 48rem">
            <h3 class="mb-1">Operating scoreboard</h3>
            <p class="text-secondary small">Blank means this shop has not set a target. The scoreboard shows the actual number and does not mark it good or behind. A blank stall age uses the median cycle target. If that is also blank, the stalled queue stays off.</p>
            <div class="row g-3">
                @foreach ([
                    ['opportunity_ros_per_open_day', 'ROs / open day', '0', '999', null],
                    ['dollar_close_target_percent', 'Dollar close', '0', '100', '%'],
                    ['sold_labor_hours_per_open_day', 'Sold hours / open day', '0', '999', null],
                    ['median_cycle_target_days', 'Median cycle', '0', '365', 'days'],
                    ['stalled_ro_age_days', 'Stall age', '0.1', '365', 'days'],
                ] as [$scoreboardName, $scoreboardLabel, $scoreboardMin, $scoreboardMax, $scoreboardUnit])
                    <div class="col-12 col-sm-6">
                        <label for="excellence-{{ $scoreboardName }}" class="form-label">{{ $scoreboardLabel }}</label>
                        <div @class(['input-group' => $scoreboardUnit !== null, 'has-validation' => $scoreboardUnit !== null && $errors->has($scoreboardName)])>
                            <input id="excellence-{{ $scoreboardName }}" type="number" name="{{ $scoreboardName }}" min="{{ $scoreboardMin }}" max="{{ $scoreboardMax }}" step="0.1" value="{{ old($scoreboardName, $excellenceTargets[$scoreboardName]) }}" @class($excellenceField($scoreboardName))>
                            @if ($scoreboardUnit !== null)
                                <span class="input-group-text">{{ $scoreboardUnit }}</span>
                            @endif
                            @error($scoreboardName)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endforeach
            </div>
            <label class="form-check mt-3 mb-0">
                <input type="checkbox" name="parts_margin_target_active" value="1" @checked(old('parts_margin_target_active', $excellenceTargets['parts_margin_target_active'])) class="form-check-input">
                <span class="form-check-label">Judge parts margin on the scoreboard against the parts margin target above</span>
            </label>
        </div>
    </div>

    <div class="card-body">
        <div style="max-width: 48rem">
            <h3 class="mb-1">Owner digest and review</h3>
            <p class="text-secondary small">Daily digest timing and quarterly target review.</p>

            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6">
                    <label class="form-check form-switch mb-0">
                        <input
                            type="checkbox"
                            name="owner_digest_enabled"
                            value="1"
                            @checked(old('owner_digest_enabled', $excellenceTargets['owner_digest_enabled']))
                            class="form-check-input"
                        >
                        <span class="form-check-label">Email daily owner digest</span>
                    </label>
                </div>

                <div class="col-12 col-sm-6">
                    <label for="excellence-owner-digest-time" class="form-label required">Digest send time (shop time)</label>
                    <input
                        id="excellence-owner-digest-time"
                        type="time"
                        name="owner_digest_time"
                        required
                        value="{{ old('owner_digest_time', $excellenceTargets['owner_digest_time']) }}"
                        @class($excellenceField('owner_digest_time'))
                    >
                    @error('owner_digest_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-top pt-3 mt-3">
                <label class="form-check mb-0">
                    <input
                        type="checkbox"
                        name="mark_target_reviewed"
                        value="1"
                        class="form-check-input"
                    >
                    <span class="form-check-label">Mark quarterly target review complete today</span>
                </label>
                @if ($excellenceTargetReview)
                    <span class="small text-secondary">Last review: {{ $excellenceTargetReview }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="card-footer">
        <div class="btn-list justify-content-end">
            <a href="{{ route('operations.reports.operational', ['tab' => 'margin-health']) }}" class="btn">Open Margin Health</a>
            <a href="{{ route('operations.owner.day-review') }}" class="btn">Day Review</a>
            <button type="submit" class="btn btn-primary">Save owner targets</button>
        </div>
    </div>
</form>
