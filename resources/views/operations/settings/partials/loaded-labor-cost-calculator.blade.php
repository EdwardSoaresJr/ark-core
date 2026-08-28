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
    class="mt-2 border border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-700"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="font-bold uppercase tracking-[0.08em] text-slate-500">Estimated labor cost</p>
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">
                Margin planning only — not a paycheck.
                Output is cost per <strong class="font-semibold text-slate-700">billed</strong> hour for labor GP and efficiency.
                <span x-show="isHourlyPay()">Hourly: spread clock wage across billable utilization, then add shop overhead.</span>
                <span x-show="isFlagPay()" x-cloak>Flag: take the higher of flag rate and floor ÷ utilization, then burden and overhead.</span>
            </p>
        </div>
        <p class="shrink-0 text-right">
            <span class="block text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Est. cost / billed hr</span>
            <span class="text-lg font-black tabular-nums text-slate-950" x-text="formattedResult()"></span>
        </p>
    </div>

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <label class="block sm:col-span-2">
            <span class="font-semibold text-slate-700">Pay type</span>
            <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">How this technician is paid. Estimated labor cost is for margin — not payroll settlement.</span>
            {{-- Hidden field owns form submit; Alpine radios often fail to post with x-model alone. --}}
            <input type="hidden" name="labor_pay_basis" :value="payBasis">
            <div class="mt-1.5 flex flex-wrap gap-4">
                @foreach (\App\Ark\Operations\Labor\TechnicianLaborPayBasis::cases() as $basis)
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input
                            type="radio"
                            value="{{ $basis->value }}"
                            x-model="payBasis"
                            class="rounded-full border-slate-300 text-slate-950 focus:ring-slate-400"
                        >
                        {{ $basis->label() }}
                    </label>
                @endforeach
            </div>
        </label>

        <template x-if="isFlagPay()">
            <div class="sm:col-span-2 grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="font-semibold text-slate-700">Flag rate</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">What the technician earns for completed flagged production.</span>
                    <input name="flag_rate" x-model="flagRate" type="number" min="0" step="0.01" placeholder="30.00" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                </label>
                <label class="block">
                    <span class="font-semibold text-slate-700">Hourly floor</span>
                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">The hourly amount the technician is guaranteed for time worked when flag pay does not cover it.</span>
                    <input name="floor_rate" x-model="floorRate" type="number" min="0" step="0.01" placeholder="{{ TechnicianFloorWageSuggestion::formattedDollars() }}" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                </label>
                <div x-show="floorNeedsReview()" x-cloak class="sm:col-span-2 border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] leading-4 text-amber-950">
                    <p class="font-bold uppercase tracking-[0.06em] text-amber-800">Hourly floor may need review</p>
                    <p class="mt-1 tabular-nums">
                        Stored: $<span x-text="Number(floorRate).toFixed(2)"></span>
                        · Current shop suggestion: $<span x-text="Number(floorSuggestion).toFixed(2)"></span>
                    </p>
                    <p class="mt-1 text-amber-800/90" x-text="floorSuggestionLabel"></p>
                </div>
            </div>
        </template>

        <label class="block" x-show="isHourlyPay()">
            <span class="font-semibold text-slate-700" x-text="basePayLabel()"></span>
            <span class="mt-0.5 block text-[11px] leading-4 text-slate-500" x-text="basePayHint()"></span>
            <input x-model="baseWage" type="number" min="0" step="0.01" placeholder="30.00" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
        </label>
        <label class="block">
            <span class="font-semibold text-slate-700">Burden %</span>
            <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Employer cost on top of wage: payroll tax, benefits, workers comp, retirement match. Most shops land around 25–35%; default is 28%.</span>
            <input x-model="burdenPercent" type="number" min="0" step="0.1" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
        </label>
        <label class="block">
            <span class="font-semibold text-slate-700">Overhead / billed hr</span>
            <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Shop cost per billed hour from <strong class="font-semibold text-slate-700">Settings → Shop Overhead</strong>. Prefills automatically when set.</span>
            <input x-model="overheadPerHour" type="number" min="0" step="0.01" placeholder="5.00" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
        </label>
        <label class="block" x-show="isHourlyPay() || isFlagPay()">
            <span class="font-semibold text-slate-700">Billable utilization %</span>
            <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">
                <span x-show="isHourlyPay()">How much paid clock time becomes billable hours. Non-billable time includes cleanup, comebacks, training, and shop tasks. 85% on an {{ number_format((float) $workdayHours, 1) }}-hr day ≈ {{ number_format((float) $workdayHours * 0.85, 1) }} billable hrs.</span>
                <span x-show="isFlagPay()" x-cloak>Assumed flagged/billed hours ÷ compensable hours. Used only to estimate what the hourly floor costs per produced hour — not a pay rate.</span>
            </span>
            <input x-model="billableUtilization" type="number" min="1" max="100" step="1" class="mt-1.5 w-full rounded-sm border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
        </label>
    </div>

    <div x-show="calculationBreakdown()" x-cloak class="mt-3 space-y-1 border-t border-slate-200 pt-3 text-[11px] leading-4 text-slate-600">
        <p class="font-bold uppercase tracking-[0.08em] text-slate-400">How this adds up</p>
        <template x-if="calculationBreakdown()">
            <div class="space-y-0.5 tabular-nums">
                <p x-show="calculationBreakdown().showsFloorEquivalent">
                    <span class="text-slate-500">Floor-equivalent production cost</span>
                    <span class="float-right font-semibold text-slate-800" x-text="`${money(calculationBreakdown().floorEquivalent)}/billed hr`"></span>
                    <span class="block text-slate-400" x-text="`floor ÷ ${calculationBreakdown().utilization}% utilization — planning only`"></span>
                </p>
                <p x-show="isFlagPay()">
                    <span class="text-slate-500">Effective wage cost</span>
                    <span class="float-right font-semibold text-slate-800" x-text="`${money(calculationBreakdown().effectiveWage)}/billed hr`"></span>
                    <span class="block text-slate-400">max(flag rate, floor-equivalent) — not a stored pay rate</span>
                </p>
                <p>
                    <span class="text-slate-500">After burden</span>
                    <span class="float-right font-semibold text-slate-800" x-text="`${money(calculationBreakdown().payrollLoaded)}/hr`"></span>
                    <span class="block text-slate-400">effective × (1 + burden)</span>
                </p>
                <p x-show="calculationBreakdown().usesUtilization">
                    <span class="text-slate-500">÷ utilization</span>
                    <span class="float-right font-semibold text-slate-800" x-text="`${money(calculationBreakdown().afterUtilization)}/hr`"></span>
                    <span class="block text-slate-400" x-text="`spread across ${calculationBreakdown().utilization}% billable time`"></span>
                </p>
                <p x-show="calculationBreakdown().overhead > 0">
                    <span class="text-slate-500">+ overhead</span>
                    <span class="float-right font-semibold text-slate-800" x-text="money(calculationBreakdown().overhead)"></span>
                </p>
                <p class="border-t border-slate-200 pt-1">
                    <span class="font-semibold text-slate-700">Estimated labor cost / billed hr</span>
                    <span class="float-right font-black text-slate-950" x-text="`${money(calculationBreakdown().total)}/hr`"></span>
                </p>
            </div>
        </template>
    </div>

    @if ($showApply && $targetInputId)
        <div class="mt-3 flex items-center justify-end gap-3">
            <p x-show="applied" x-cloak class="text-[11px] font-semibold text-emerald-700">Applied to estimated labor cost.</p>
            <button
                type="button"
                class="ops-index-btn ops-index-btn--ghost disabled:cursor-not-allowed disabled:opacity-50"
                @click.prevent="applyLoadedCost()"
                :disabled="! canApply()"
            >
                Use estimated labor cost
            </button>
        </div>
        <p class="mt-2 text-[11px] leading-4 text-slate-500">Or just <strong class="font-semibold text-slate-700">Save changes</strong> — if the calculator has a rate, ARK applies the estimated labor cost automatically.</p>
    @endif
</div>
