@props([
    'laborCategories' => [],
    'selectedLaborCategoryKey' => null,
])

@php
    use App\Ark\Operations\EstimatePricing\LaborRateOverrideReason;
    use App\Ark\Operations\Labor\LaborAdjustment;
    use App\Ark\Operations\Labor\LaborAdjustmentReason;
@endphp

<div class="d-flex flex-column gap-2" data-labor-authority>
    <div class="row g-2">
        <label class="col-6">
            <span class="form-label">Book hours</span>
            <input
                name="labor_entered_hours"
                x-model="laborEnteredHours"
                @input="onLaborAuthorityInput()"
                required
                inputmode="decimal"
                placeholder="1.50"
                class="form-control text-end ark-money"
            >
        </label>
        <label class="col-6">
            <span class="form-label">Billable hours</span>
            <input
                name="quantity"
                x-model="laborFinalHours"
                @input="onLaborFinalHoursInput()"
                required
                inputmode="decimal"
                :readonly="! laborCategoryAllowsModifiers()"
                class="form-control text-end ark-money"
                :class="! laborCategoryAllowsModifiers() ? 'bg-secondary-lt' : ''"
            >
        </label>
    </div>

    <div class="row g-2">
        <label class="col-6">
            <span class="form-label">Rate</span>
            <input
                name="unit_price"
                x-model="sell"
                @input="onSellInput(); onLaborAuthorityInput()"
                required
                inputmode="decimal"
                placeholder="165.00"
                :readonly="! laborCategoryAllowsModifiers()"
                class="form-control text-end ark-money"
                :class="! laborCategoryAllowsModifiers() ? 'bg-secondary-lt' : ''"
            >
        </label>
        <label class="col-6">
            <span class="form-label">Labor category</span>
            <select
                name="labor_category_key"
                x-model="laborCategoryKey"
                @change="onLaborCategoryChange()"
                required
                class="form-select"
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
        class="form-hint mb-0"
    >Program category - book hours and category rate apply. Adjust labor is hidden here so program billing stays clean.</p>

    <input type="hidden" name="labor_rate_overridden" :value="sellEdited && laborCategoryAllowsModifiers() ? '1' : '0'">
    <input type="hidden" name="labor_hours_overridden" :value="laborHoursOverridden ? '1' : '0'">

    <div class="d-flex flex-wrap align-items-center gap-3 small">
        <button
            type="button"
            x-show="laborCategoryAllowsModifiers() && laborCanBillEnteredHours()"
            x-cloak
            class="btn btn-link btn-sm p-0"
            @click="billEnteredHours()"
        >Bill book hours (<span x-text="laborEnteredHours"></span>)</button>
        <button
            type="button"
            x-show="laborCategoryAllowsModifiers() && laborHoursOverridden"
            x-cloak
            class="btn btn-link btn-sm p-0"
            @click="useCalculatedLaborHours()"
        >Use calculated hours</button>
        <button
            type="button"
            x-show="laborCategoryAllowsModifiers() && sellEdited"
            x-cloak
            class="btn btn-link btn-sm p-0"
            @click="usePolicyLaborRate()"
        >Use policy rate</button>
        <button
            type="button"
            x-show="laborCategoryAllowsModifiers()"
            x-cloak
            class="btn btn-link btn-sm p-0"
            @click="toggleLaborAdjustExpanded()"
            :aria-expanded="laborAdjustExpanded"
            x-text="laborAdjustExpanded ? 'Hide difficulty adjustment' : 'Adjust difficulty'"
        ></button>
        <span
            x-show="laborMinimumMessage()"
            x-cloak
            class="text-warning fw-medium"
            x-text="laborMinimumMessage()"
        ></span>
        <span
            x-show="laborRoundingMessage()"
            x-cloak
            class="text-warning fw-medium"
            x-text="laborRoundingMessage()"
        ></span>
    </div>

    <label class="d-block" x-show="laborHoursOverridden && laborCategoryAllowsModifiers()" x-cloak>
        <span class="form-label">Hours override reason</span>
        <input
            name="labor_override_reason"
            x-model="laborOverrideReason"
            :required="laborHoursOverridden"
            placeholder="Why billable hours differ from category rules"
            class="form-control"
        >
    </label>

    <div x-show="sellEdited && laborCategoryAllowsModifiers()" x-cloak>
        <p class="small text-warning fw-medium mb-1">
            Custom rate - choose a reason before saving (e.g. Menu / package price for a $199 PPI).
        </p>
        <label class="d-block">
            <span class="form-label">Rate override reason</span>
            <select
                name="labor_rate_override_reason"
                x-model="laborRateOverrideReason"
                :required="sellEdited && laborCategoryAllowsModifiers()"
                @class(['form-select', 'is-invalid' => $errors->has('labor_rate_override_reason')])
            >
                <option value="">Select reason</option>
                @foreach (LaborRateOverrideReason::cases() as $reason)
                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                @endforeach
            </select>
            @error('labor_rate_override_reason')
                <span class="invalid-feedback d-block">{{ $message }}</span>
            @enderror
        </label>
    </div>

    <div x-show="laborAdjustExpanded && laborCategoryAllowsModifiers()" x-cloak>
        <div class="d-flex flex-column gap-3 border-top pt-3">
            <div class="row g-2">
                <label class="col-6">
                    <span class="form-label">Difficulty</span>
                    <select
                        name="labor_adjustment"
                        x-model="laborAdjustment"
                        @change="onLaborAuthorityInput()"
                        :disabled="!laborAdjustExpanded"
                        class="form-select"
                    >
                        @foreach (LaborAdjustment::cases() as $adjustment)
                            <option value="{{ $adjustment->value }}">{{ $adjustment->selectLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="col-6" x-show="laborAdjustment === 'custom'" x-cloak>
                    <span class="form-label">Custom factor</span>
                    <input
                        name="labor_adjustment_factor"
                        x-model="laborCustomFactor"
                        @input="onLaborAuthorityInput()"
                        inputmode="decimal"
                        placeholder="1.35"
                        :disabled="!laborAdjustExpanded || laborAdjustment !== 'custom'"
                        class="form-control text-end ark-money"
                    >
                </label>
            </div>

            <div x-show="laborAdjustmentRequiresReason()" x-cloak>
                <div class="row g-2">
                    <label class="col-6">
                        <span class="form-label">Adjustment reason</span>
                        <select
                            name="labor_adjustment_reason"
                            x-model="laborReason"
                            @change="onLaborAuthorityInput()"
                            :disabled="!laborAdjustExpanded || !laborAdjustmentRequiresReason()"
                            class="form-select"
                        >
                            <option value="">Select reason</option>
                            @foreach (LaborAdjustmentReason::cases() as $reason)
                                <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="col-6" x-show="laborReason === 'custom'" x-cloak>
                        <span class="form-label">Custom reason</span>
                        <input
                            name="labor_adjustment_reason_custom"
                            x-model="laborReasonCustom"
                            @input="onLaborAuthorityInput()"
                            placeholder="Describe access or difficulty"
                            :disabled="!laborAdjustExpanded || laborReason !== 'custom'"
                            class="form-control"
                        >
                    </label>
                </div>
            </div>
        </div>
    </div>

    <template x-if="! laborAdjustExpanded">
        <input type="hidden" name="labor_adjustment" :value="laborAdjustment">
    </template>

    <p
        x-show="laborAuthorityPreview()"
        x-cloak
        class="form-hint mb-0"
        data-labor-authority-preview
        x-text="laborAuthorityPreview()"
    ></p>
</div>
