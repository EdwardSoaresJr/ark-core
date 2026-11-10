@php
    /** @var array $laborWorkTypeMatrix */
    $matrix = $laborWorkTypeMatrix ?? [
        'work_types' => [],
        'columns' => [],
        'cells' => [],
        'standard_rate_cents' => 0,
        'billing_minimums' => [],
        'labor_pricing_profile_version' => 0,
    ];
    $standardDisplay = number_format(($matrix['standard_rate_cents'] ?? 0) / 100, 2, '.', '');
@endphp

<div class="mb-4" data-settings-labor-matrix>
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <div>
            <h3 class="mb-1">Labor rate matrix</h3>
            <p class="text-secondary small mb-0">
                Work type × billing class. Use Standard follows the shop hourly rate.
                Programming and Troubleshooting share one operation-class rate until Core splits them.
            </p>
        </div>
        <div style="max-width: 14rem" class="w-100">
            <label for="standard-hourly-rate" class="form-label mb-1">Standard hourly rate</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input
                    id="standard-hourly-rate"
                    name="standard_hourly_rate"
                    value="{{ old('standard_hourly_rate', $standardDisplay) }}"
                    required
                    inputmode="decimal"
                    class="form-control font-monospace @error('standard_hourly_rate') is-invalid @enderror"
                    data-labor-standard-rate
                >
            </div>
            @error('standard_hourly_rate')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="table-responsive border rounded">
        <table class="table table-vcenter table-sm mb-0" data-labor-rate-matrix>
            <thead>
                <tr>
                    <th class="bg-light">Work type</th>
                    @foreach ($matrix['columns'] as $column)
                        <th class="bg-light text-center">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($matrix['work_types'] as $workType)
                    @php
                        $linked = $workType['shares_class_with'] ?? null;
                    @endphp
                    <tr data-labor-work-type="{{ $workType['key'] }}">
                        <td class="fw-medium">
                            {{ $workType['label'] }}
                            @if ($linked)
                                <div class="form-hint mb-0">Same rate as {{ collect($matrix['work_types'])->firstWhere('key', $linked)['label'] ?? 'linked row' }}</div>
                            @endif
                        </td>
                        @foreach ($matrix['columns'] as $column)
                            @php
                                $cell = $matrix['cells'][$workType['key']][$column['value']] ?? [];
                                $mode = old(
                                    'matrix.'.$workType['key'].'.'.$column['value'].'.mode',
                                    $cell['settings_mode'] ?? ($cell['follows_shop_standard'] ?? false ? 'standard' : 'custom'),
                                );
                                if ($mode === null || $mode === '') {
                                    $mode = ($cell['follows_shop_standard'] ?? false) ? 'standard' : 'custom';
                                }
                                $rateValue = old(
                                    'matrix.'.$workType['key'].'.'.$column['value'].'.rate',
                                    isset($cell['effective_rate_cents'])
                                        ? number_format($cell['effective_rate_cents'] / 100, 2, '.', '')
                                        : $standardDisplay,
                                );
                                $disabled = $linked !== null;
                            @endphp
                            <td class="align-top" style="min-width: 9.5rem">
                                @if ($disabled)
                                    <div class="font-monospace text-center py-2" title="Linked to {{ $linked }}">
                                        {{ $cell['rate_display'] ?? '—' }}
                                    </div>
                                @else
                                    <div
                                        x-data="{ mode: @js($mode) }"
                                        class="vstack gap-1"
                                    >
                                        <select
                                            name="matrix[{{ $workType['key'] }}][{{ $column['value'] }}][mode]"
                                            class="form-select form-select-sm"
                                            x-model="mode"
                                            aria-label="{{ $workType['label'] }} {{ $column['label'] }} rate mode"
                                        >
                                            <option value="standard">Use Standard</option>
                                            <option value="custom">Custom</option>
                                            <option value="zero">Zero</option>
                                        </select>
                                        <div class="input-group input-group-sm" x-show="mode === 'custom'" x-cloak>
                                            <span class="input-group-text">$</span>
                                            <input
                                                type="text"
                                                name="matrix[{{ $workType['key'] }}][{{ $column['value'] }}][rate]"
                                                value="{{ $rateValue }}"
                                                inputmode="decimal"
                                                class="form-control font-monospace"
                                                aria-label="{{ $workType['label'] }} {{ $column['label'] }} custom rate"
                                            >
                                        </div>
                                        <div class="form-hint mb-0 text-center" x-show="mode === 'standard'" x-cloak>
                                            Standard
                                        </div>
                                        <div class="form-hint mb-0 text-center" x-show="mode === 'zero'" x-cloak>
                                            $0.00
                                        </div>
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="mb-4" data-settings-labor-minimums>
    <h3 class="mb-1">Billing-class minimums</h3>
    <p class="text-secondary small mb-3">
        Per billing class only — Retail hours do not satisfy a Warranty minimum, and vice versa.
        Shortfall uses the Retail Mechanical rate by default (configurable below).
        Applies only to repair orders created after you activate a minimum.
        @if (($matrix['labor_pricing_profile_version'] ?? 0) >= 1)
            <span class="badge bg-azure-lt text-azure ms-1">Profile v{{ $matrix['labor_pricing_profile_version'] }} active for new ROs</span>
        @endif
    </p>

    <div class="list-group list-group-flush border rounded">
        @foreach (\App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrix::minimumConfigurablePostures() as $posture)
            @php
                $row = $matrix['billing_minimums'][$posture->value] ?? [
                    'mode' => 'none',
                    'hours' => '0.00',
                    'shortfall_rate_source' => 'standard_mechanical',
                    'shortfall_rate_cents' => $matrix['standard_rate_cents'] ?? 0,
                ];
                $label = \App\Ark\Operations\EstimatePricing\LaborWorkTypeMatrix::billingColumnLabel($posture);
                $mode = old('billing_minimums.'.$posture->value.'.mode', $row['mode']);
                $hours = old('billing_minimums.'.$posture->value.'.hours', $row['hours']);
                $source = old('billing_minimums.'.$posture->value.'.shortfall_rate_source', $row['shortfall_rate_source'] ?? 'standard_mechanical');
                $customRate = old(
                    'billing_minimums.'.$posture->value.'.shortfall_rate',
                    number_format(($row['shortfall_rate_cents'] ?? $matrix['standard_rate_cents'] ?? 0) / 100, 2, '.', ''),
                );
            @endphp
            <div
                class="list-group-item"
                data-billing-minimum="{{ $posture->value }}"
                x-data="{ mode: @js($mode), source: @js($source) }"
            >
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <div class="fw-medium">{{ $label }}</div>
                        <div class="form-hint mb-0">{{ $posture->label() }}</div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label" for="min-mode-{{ $posture->value }}">Minimum</label>
                        <select
                            id="min-mode-{{ $posture->value }}"
                            name="billing_minimums[{{ $posture->value }}][mode]"
                            class="form-select"
                            x-model="mode"
                        >
                            @foreach (\App\Ark\Operations\EstimatePricing\LaborBillingMinimumMode::cases() as $minMode)
                                <option value="{{ $minMode->value }}">{{ $minMode->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-sm-3 col-md-2" x-show="mode !== 'none'" x-cloak>
                        <label class="form-label" for="min-hours-{{ $posture->value }}">Hours</label>
                        <input
                            id="min-hours-{{ $posture->value }}"
                            name="billing_minimums[{{ $posture->value }}][hours]"
                            value="{{ $hours }}"
                            inputmode="decimal"
                            class="form-control font-monospace"
                        >
                    </div>
                    <div class="col-12 col-md-4" x-show="mode !== 'none'" x-cloak>
                        <label class="form-label" for="min-source-{{ $posture->value }}">Shortfall rate</label>
                        <select
                            id="min-source-{{ $posture->value }}"
                            name="billing_minimums[{{ $posture->value }}][shortfall_rate_source]"
                            class="form-select mb-1"
                            x-model="source"
                        >
                            <option value="standard_mechanical">Retail Mechanical (default)</option>
                            <option value="custom">Custom rate</option>
                        </select>
                        <div class="input-group input-group-sm" x-show="source === 'custom'" x-cloak>
                            <span class="input-group-text">$</span>
                            <input
                                type="text"
                                name="billing_minimums[{{ $posture->value }}][shortfall_rate]"
                                value="{{ $customRate }}"
                                inputmode="decimal"
                                class="form-control font-monospace"
                                aria-label="{{ $label }} shortfall custom rate"
                            >
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
