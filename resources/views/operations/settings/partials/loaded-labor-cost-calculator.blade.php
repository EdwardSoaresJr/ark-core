@props([
    'targetInputId' => null,
    'workdayHours' => 8,
    'showApply' => true,
    'shopOverheadPerHour' => null,
    'laborPayBasis' => 'hourly',
    'flagRate' => null,
    'floorRate' => null,
    'seedFloorSuggestion' => false,
])

@php
    use App\Ark\Operations\Labor\TechnicianFloorWageSuggestion;

    $floorSuggestionDollars = TechnicianFloorWageSuggestion::dollars();
    $resolvedFloorRate = $floorRate;
    if ($resolvedFloorRate === null || $resolvedFloorRate === '') {
        $resolvedFloorRate = $seedFloorSuggestion ? TechnicianFloorWageSuggestion::formattedDollars() : '';
    }
    $calculatorField = 'loaded-labor-'.($targetInputId ?? 'calculator');
@endphp

<div
    x-data="loadedLaborCostCalculator({
        targetInputId: @js($targetInputId),
        shopOverheadPerHour: @js($shopOverheadPerHour),
        laborPayBasis: @js($laborPayBasis),
        flagRate: @js($flagRate !== null && $flagRate !== '' ? (string) $flagRate : ''),
        floorRate: @js($resolvedFloorRate !== null && $resolvedFloorRate !== '' ? (string) $resolvedFloorRate : ''),
        floorSuggestion: @js($floorSuggestionDollars),
        floorSuggestionLabel: @js(TechnicianFloorWageSuggestion::label()),
        burdenPercent: {{ \App\Ark\Operations\Labor\LoadedLaborCostCalculator::DEFAULT_BURDEN_PERCENT }},
        billableUtilization: {{ \App\Ark\Operations\Labor\LoadedLaborCostCalculator::DEFAULT_BILLABLE_UTILIZATION_PERCENT }},
    })"
    class="card card-sm mt-3"
    data-loaded-labor-calculator
>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div style="max-width: 36rem">
                <div class="subheader">Estimated labor cost</div>
                <p class="small text-secondary mb-0">
                    Margin planning only - not a paycheck.
                    Output is cost per <strong>billed</strong> hour for labor GP and efficiency.
                    <span x-show="isHourlyPay()">Hourly: spread clock wage across billable utilization, then add shop overhead.</span>
                    <span x-show="isFlagPay()" x-cloak>Flag: take the higher of flag rate and floor ÷ utilization, then burden and overhead.</span>
                </p>
            </div>
            <div class="text-end">
                <div class="subheader">Est. cost / billed hr</div>
                <div class="h2 mb-0 font-monospace" x-text="formattedResult()" data-loaded-labor-result></div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <fieldset class="col-12">
                <legend class="form-label mb-1">Pay type</legend>
                <div class="small text-secondary mb-2">How this technician is paid. Estimated labor cost is for margin - not payroll settlement.</div>
                {{-- Hidden field owns form submit; Alpine radios often fail to post with x-model alone. --}}
                <input type="hidden" name="labor_pay_basis" :value="payBasis">
                <div>
                    @foreach (\App\Ark\Operations\Labor\TechnicianLaborPayBasis::cases() as $basis)
                        <label class="form-check form-check-inline">
                            <input
                                type="radio"
                                value="{{ $basis->value }}"
                                x-model="payBasis"
                                class="form-check-input"
                            >
                            <span class="form-check-label">{{ $basis->label() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <template x-if="isFlagPay()">
                <div class="col-12">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="{{ $calculatorField }}-flag-rate" class="form-label">Flag rate</label>
                            <input id="{{ $calculatorField }}-flag-rate" name="flag_rate" x-model="flagRate" type="number" min="0" step="0.01" placeholder="30.00" class="form-control">
                            <div class="form-hint">What the technician earns for completed flagged production.</div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="{{ $calculatorField }}-floor-rate" class="form-label">Hourly floor</label>
                            <input id="{{ $calculatorField }}-floor-rate" name="floor_rate" x-model="floorRate" type="number" min="0" step="0.01" placeholder="{{ TechnicianFloorWageSuggestion::formattedDollars() }}" class="form-control">
                            <div class="form-hint">The hourly amount the technician is guaranteed for time worked when flag pay does not cover it.</div>
                        </div>
                        <div x-show="floorNeedsReview()" x-cloak class="col-12">
                            <div class="alert alert-warning small mb-0" role="status">
                                <div class="fw-bold">Hourly floor may need review</div>
                                <div class="font-monospace">
                                    Stored: $<span x-text="Number(floorRate).toFixed(2)"></span>
                                    · Current shop suggestion: $<span x-text="Number(floorSuggestion).toFixed(2)"></span>
                                </div>
                                <div x-text="floorSuggestionLabel"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div class="col-12 col-sm-6" x-show="isHourlyPay()">
                <label for="{{ $calculatorField }}-base-wage" class="form-label" x-text="basePayLabel()"></label>
                <input id="{{ $calculatorField }}-base-wage" x-model="baseWage" type="number" min="0" step="0.01" placeholder="30.00" class="form-control">
                <div class="form-hint" x-text="basePayHint()"></div>
            </div>
            <div class="col-12 col-sm-6">
                <div class="d-flex align-items-center gap-1 mb-2">
                    <label for="{{ $calculatorField }}-burden" class="form-label mb-0">Burden %</label>
                    <x-tabler.help-tip
                        text="Employer cost on top of wage: payroll tax, benefits, workers comp, retirement match. Most shops land around 25–35%; default is 28%."
                        label="Burden help"
                    />
                </div>
                <input id="{{ $calculatorField }}-burden" x-model="burdenPercent" type="number" min="0" step="0.1" class="form-control">
            </div>
            <div class="col-12 col-sm-6">
                <div class="d-flex align-items-center gap-1 mb-2">
                    <label for="{{ $calculatorField }}-overhead" class="form-label mb-0">Overhead / billed hr</label>
                    <x-tabler.help-tip
                        text="Shop cost per billed hour from Settings → Shop Overhead. Prefills automatically when set."
                        label="Overhead per billed hour help"
                    />
                </div>
                <input id="{{ $calculatorField }}-overhead" x-model="overheadPerHour" type="number" min="0" step="0.01" placeholder="5.00" class="form-control">
            </div>
            <div class="col-12 col-sm-6" x-show="isHourlyPay() || isFlagPay()">
                <label for="{{ $calculatorField }}-utilization" class="form-label">Billable utilization %</label>
                <input id="{{ $calculatorField }}-utilization" x-model="billableUtilization" type="number" min="1" max="100" step="1" class="form-control">
                <div class="form-hint">
                    <span x-show="isHourlyPay()">How much paid clock time becomes billable hours. Non-billable time includes cleanup, comebacks, training, and shop tasks. 85% on an {{ number_format((float) $workdayHours, 1) }}-hr day ≈ {{ number_format((float) $workdayHours * 0.85, 1) }} billable hrs.</span>
                    <span x-show="isFlagPay()" x-cloak>Assumed flagged/billed hours ÷ compensable hours. Used only to estimate what the hourly floor costs per produced hour - not a pay rate.</span>
                </div>
            </div>
        </div>

        <div x-show="calculationBreakdown()" x-cloak class="border-top pt-3 mt-3 small" data-loaded-labor-breakdown>
            <div class="subheader mb-2">How this adds up</div>
            <template x-if="calculationBreakdown()">
                <div class="font-monospace">
                    <div class="mb-1" x-show="calculationBreakdown().showsFloorEquivalent">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-secondary">Floor-equivalent production cost</span>
                            <span class="fw-medium" x-text="`${money(calculationBreakdown().floorEquivalent)}/billed hr`"></span>
                        </div>
                        <div class="text-secondary" x-text="`floor ÷ ${calculationBreakdown().utilization}% utilization - planning only`"></div>
                    </div>
                    <div class="mb-1" x-show="isFlagPay()">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-secondary">Effective wage cost</span>
                            <span class="fw-medium" x-text="`${money(calculationBreakdown().effectiveWage)}/billed hr`"></span>
                        </div>
                        <div class="text-secondary">max(flag rate, floor-equivalent) - not a stored pay rate</div>
                    </div>
                    <div class="mb-1">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-secondary">After burden</span>
                            <span class="fw-medium" x-text="`${money(calculationBreakdown().payrollLoaded)}/hr`"></span>
                        </div>
                        <div class="text-secondary">effective × (1 + burden)</div>
                    </div>
                    <div class="mb-1" x-show="calculationBreakdown().usesUtilization">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="text-secondary">÷ utilization</span>
                            <span class="fw-medium" x-text="`${money(calculationBreakdown().afterUtilization)}/hr`"></span>
                        </div>
                        <div class="text-secondary" x-text="`spread across ${calculationBreakdown().utilization}% billable time`"></div>
                    </div>
                    <div class="mb-1 d-flex justify-content-between gap-2" x-show.important="calculationBreakdown().overhead > 0">
                        <span class="text-secondary">+ overhead</span>
                        <span class="fw-medium" x-text="money(calculationBreakdown().overhead)"></span>
                    </div>
                    <div class="d-flex justify-content-between gap-2 border-top pt-1">
                        <span class="fw-medium">Estimated labor cost / billed hr</span>
                        <span class="fw-bold" x-text="`${money(calculationBreakdown().total)}/hr`"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    @if ($showApply && $targetInputId)
        <div class="card-footer">
            <div class="d-flex flex-wrap align-items-center justify-content-end gap-3">
                <span x-show="applied" x-cloak class="small text-success fw-medium" role="status">Applied to estimated labor cost.</span>
                <button
                    type="button"
                    class="btn btn-sm"
                    @click.prevent="applyLoadedCost()"
                    :disabled="! canApply()"
                >
                    Use estimated labor cost
                </button>
            </div>
            <div class="small text-secondary mt-2">Or just <strong>Save changes</strong> - if the calculator has a rate, ARK applies the estimated labor cost automatically.</div>
        </div>
    @endif
</div>
