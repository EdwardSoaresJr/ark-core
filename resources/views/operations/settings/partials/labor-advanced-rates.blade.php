{{-- Advanced billing rates — shop-default operation class + specialty matrix --}}
@php
    /** @var array $laborPoliciesMatrix */
    /** @var array $laborPolicyPreview */
    $matrix = $laborPoliciesMatrix;
    $preview = $laborPolicyPreview;
    $defaultClassKey = $matrix['shop_default']['operation_class_key']
        ?? ($matrix['operation_classes'][0]['key'] ?? 'general_repair');
    $defaultClassName = $matrix['shop_default']['operation_class_name']
        ?? ($matrix['operation_classes'][0]['name'] ?? 'General repair');
    $standardCents = (int) ($matrix['standard_rate_cents'] ?? $settings->defaultLaborRateCents());
@endphp

<div class="mb-3" data-settings-labor-advanced-rates>
    <p class="text-secondary small mb-3">
        These rates apply when a concern uses a billing type other than everyday Customer Pay —
        or when a billing class needs its own explicit hourly rate on the Labor matrix.
        Each row is for your shop’s default operation class
        (<strong>{{ $defaultClassName }}</strong>).
        Choose <strong>Use Standard</strong>, <strong>Custom Rate</strong>, or
        <strong>Intentional Zero</strong> (no customer charge). Matching dollar amounts alone do not mean a rate follows Standard.
        Existing estimate lines keep their snapshotted rates.
    </p>

    <div class="list-group mb-3">
        @foreach ($matrix['postures'] as $posture)
            @php
                $cell = $matrix['cells'][$defaultClassKey][$posture['value']] ?? null;
                $mode = old('advanced_rates.'.$posture['value'].'.mode', $cell['settings_mode'] ?? 'custom');
                if ($mode === null || $mode === '') {
                    $mode = ($cell['follows_shop_standard'] ?? false) ? 'standard' : 'custom';
                }
                $rateValue = old(
                    'advanced_rates.'.$posture['value'].'.rate',
                    isset($cell['effective_rate_cents'])
                        ? number_format(((int) $cell['effective_rate_cents']) / 100, 2, '.', '')
                        : number_format($standardCents / 100, 2, '.', ''),
                );
                $field = 'advanced-rate-'.$posture['value'];
            @endphp
            <div
                class="list-group-item"
                data-advanced-rate-posture="{{ $posture['value'] }}"
                x-data="{ mode: @js($mode) }"
            >
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-3">
                        <div class="fw-medium">{{ $posture['label'] }}</div>
                        <div class="small text-secondary">{{ $posture['description'] ?? '' }}</div>
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="{{ $field }}-mode" class="form-label">Rate source</label>
                        <select
                            id="{{ $field }}-mode"
                            name="advanced_rates[{{ $posture['value'] }}][mode]"
                            class="form-select"
                            x-model="mode"
                        >
                            <option value="standard">Use Standard</option>
                            <option value="custom">Custom Rate</option>
                            <option value="zero">Intentional Zero</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <label for="{{ $field }}-rate" class="form-label">Hourly rate</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input
                                id="{{ $field }}-rate"
                                name="advanced_rates[{{ $posture['value'] }}][rate]"
                                value="{{ $rateValue }}"
                                inputmode="decimal"
                                class="form-control font-monospace"
                                :readonly="mode !== 'custom'"
                                :required="mode === 'custom'"
                            >
                        </div>
                        <div class="form-hint" x-show="mode === 'standard'" x-cloak>Follows Standard hourly rate</div>
                        <div class="form-hint" x-show="mode === 'zero'" x-cloak>No customer charge</div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="subheader mb-1">Effective now</div>
                        <div class="font-monospace" data-advanced-effective>
                            {{ $cell['rate_display'] ?? '—' }}
                            <span class="text-secondary">/hr</span>
                        </div>
                        @if (! empty($cell['follows_shop_standard']))
                            <div class="small text-secondary">Uses Standard</div>
                        @elseif (($cell['settings_mode'] ?? null) === 'zero')
                            <div class="small text-secondary">Intentional zero</div>
                        @elseif (($cell['rate_type'] ?? null) !== null)
                            <div class="small text-secondary">Custom / policy</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

</div>
