@props([
    'laborCategories' => [],
    'selectedLaborCategoryKey' => null,
])

@php
    use App\Ark\Operations\EstimatePricing\LaborRateOverrideReason;
    use App\Ark\Operations\Labor\LaborAdjustment;
    use App\Ark\Operations\Labor\LaborAdjustmentReason;
@endphp

<div class="ops-labor-authority space-y-2">
    <div class="ops-line-entry-pricing-grid ops-line-entry-pricing-grid--pair">
        <label class="ops-field">
            <span class="ops-field-label">Book hours</span>
            <input
                name="labor_entered_hours"
                x-model="laborEnteredHours"
                @input="onLaborAuthorityInput()"
                required
                inputmode="decimal"
                placeholder="1.50"
                class="ops-field-input ops-field-input--numeric"
            >
        </label>
        <label class="ops-field">
            <span class="ops-field-label">Billable hours</span>
            <input
                name="quantity"
                x-model="laborFinalHours"
                @input="onLaborFinalHoursInput()"
                required
                inputmode="decimal"
                :readonly="! laborCategoryAllowsModifiers()"
                class="ops-field-input ops-field-input--numeric"
                :class="! laborCategoryAllowsModifiers() ? 'bg-slate-100/90' : ''"
            >
        </label>
    </div>

    <div class="ops-line-entry-pricing-grid ops-line-entry-pricing-grid--pair">
        <label class="ops-field">
            <span class="ops-field-label">Rate</span>
            <input
                name="unit_price"
                x-model="sell"
                @input="onSellInput(); onLaborAuthorityInput()"
                required
                inputmode="decimal"
                placeholder="165.00"
                :readonly="! laborCategoryAllowsModifiers()"
                class="ops-field-input ops-field-input--numeric"
                :class="! laborCategoryAllowsModifiers() ? 'bg-slate-100/90' : ''"
            >
        </label>
        <label class="ops-field">
            <span class="ops-field-label">Labor category</span>
            <select
                name="labor_category_key"
                x-model="laborCategoryKey"
                @change="onLaborCategoryChange()"
                required
                class="ops-field-input"
            >
                @foreach ($laborCategories as $category)
                    <option
                        value="{{ $category['key'] }}"
                        @selected($selectedLaborCategoryKey === $category['key'])
                    >{{ $category['name'] }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <p
        x-show="! laborCategoryAllowsModifiers()"
        x-cloak
        class="text-[11px] leading-4 text-slate-500"
    >Program category — book hours and category rate apply. Adjust labor is hidden here so program billing stays clean.</p>

    <input type="hidden" name="labor_rate_overridden" :value="sellEdited && laborCategoryAllowsModifiers() ? '1' : '0'">
    <input type="hidden" name="labor_hours_overridden" :value="laborHoursOverridden ? '1' : '0'">

    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            x-show="laborCategoryAllowsModifiers() && laborCanBillEnteredHours()"
            x-cloak
            class="text-[11px] font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
            @click="billEnteredHours()"
        >Bill book hours (<span x-text="laborEnteredHours"></span>)</button>
        <button
            type="button"
            x-show="laborCategoryAllowsModifiers() && laborHoursOverridden"
            x-cloak
            class="text-[11px] font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
            @click="useCalculatedLaborHours()"
        >Use calculated hours</button>
        <button
            type="button"
            x-show="laborCategoryAllowsModifiers() && sellEdited"
            x-cloak
            class="text-[11px] font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
            @click="usePolicyLaborRate()"
        >Use policy rate</button>
        <button
            type="button"
            x-show="laborCategoryAllowsModifiers()"
            x-cloak
            class="text-[11px] font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-900"
            @click="toggleLaborAdjustExpanded()"
            x-text="laborAdjustExpanded ? 'Hide difficulty adjustment' : 'Adjust difficulty'"
        ></button>
        <span
            x-show="laborMinimumMessage()"
            x-cloak
            class="text-[11px] font-semibold leading-4 text-amber-800"
            x-text="laborMinimumMessage()"
        ></span>
        <span
            x-show="laborRoundingMessage()"
            x-cloak
            class="text-[11px] font-semibold leading-4 text-amber-800"
            x-text="laborRoundingMessage()"
        ></span>
    </div>

    <label class="ops-field" x-show="laborHoursOverridden && laborCategoryAllowsModifiers()" x-cloak>
        <span class="ops-field-label">Hours override reason</span>
        <input
            name="labor_override_reason"
            x-model="laborOverrideReason"
            :required="laborHoursOverridden"
            placeholder="Why billable hours differ from category rules"
            class="ops-field-input"
        >
    </label>

    <div x-show="sellEdited && laborCategoryAllowsModifiers()" x-cloak class="space-y-1">
        <p class="text-[11px] font-semibold leading-4 text-amber-800">
            Custom rate — choose a reason before saving (e.g. Menu / package price for a $199 PPI).
        </p>
        <label class="ops-field">
            <span class="ops-field-label">Rate override reason</span>
            <select
                name="labor_rate_override_reason"
                x-model="laborRateOverrideReason"
                :required="sellEdited && laborCategoryAllowsModifiers()"
                class="ops-field-input"
            >
                <option value="">Select reason</option>
                @foreach (LaborRateOverrideReason::cases() as $reason)
                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                @endforeach
            </select>
            @error('labor_rate_override_reason')
                <span class="mt-1 block text-[11px] font-semibold text-rose-700">{{ $message }}</span>
            @enderror
        </label>
    </div>

    <div x-show="laborAdjustExpanded && laborCategoryAllowsModifiers()" x-cloak class="space-y-3 border-t border-slate-200 pt-3">
        <div class="ops-line-entry-pricing-grid ops-line-entry-pricing-grid--pair">
            <label class="ops-field">
                <span class="ops-field-label">Difficulty</span>
                <select
                    name="labor_adjustment"
                    x-model="laborAdjustment"
                    @change="onLaborAuthorityInput()"
                    :disabled="!laborAdjustExpanded"
                    class="ops-field-input"
                >
                    @foreach (LaborAdjustment::cases() as $adjustment)
                        <option value="{{ $adjustment->value }}">{{ $adjustment->selectLabel() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ops-field" x-show="laborAdjustment === 'custom'" x-cloak>
                <span class="ops-field-label">Custom factor</span>
                <input
                    name="labor_adjustment_factor"
                    x-model="laborCustomFactor"
                    @input="onLaborAuthorityInput()"
                    inputmode="decimal"
                    placeholder="1.35"
                    :disabled="!laborAdjustExpanded || laborAdjustment !== 'custom'"
                    class="ops-field-input ops-field-input--numeric"
                >
            </label>
        </div>

        <div x-show="laborAdjustmentRequiresReason()" x-cloak class="ops-line-entry-pricing-grid ops-line-entry-pricing-grid--pair">
            <label class="ops-field">
                <span class="ops-field-label">Adjustment reason</span>
                <select
                    name="labor_adjustment_reason"
                    x-model="laborReason"
                    @change="onLaborAuthorityInput()"
                    :disabled="!laborAdjustExpanded || !laborAdjustmentRequiresReason()"
                    class="ops-field-input"
                >
                    <option value="">Select reason</option>
                    @foreach (LaborAdjustmentReason::cases() as $reason)
                        <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ops-field" x-show="laborReason === 'custom'" x-cloak>
                <span class="ops-field-label">Custom reason</span>
                <input
                    name="labor_adjustment_reason_custom"
                    x-model="laborReasonCustom"
                    @input="onLaborAuthorityInput()"
                    placeholder="Describe access or difficulty"
                    :disabled="!laborAdjustExpanded || laborReason !== 'custom'"
                    class="ops-field-input"
                >
            </label>
        </div>
    </div>

    <template x-if="! laborAdjustExpanded">
        <input type="hidden" name="labor_adjustment" :value="laborAdjustment">
    </template>

    <p
        x-show="laborAuthorityPreview()"
        x-cloak
        class="ops-labor-authority-preview text-[11px] font-medium leading-4 text-slate-500"
        x-text="laborAuthorityPreview()"
    ></p>
</div>
