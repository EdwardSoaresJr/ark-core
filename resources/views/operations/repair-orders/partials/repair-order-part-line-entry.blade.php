{{-- Part line entry — expects parent form with arkPartPricing x-data --}}
<input type="hidden" name="pricing_mode" :value="pricingMode">
<input type="hidden" name="pricing_matrix_key" :value="pricingMode === 'matrix' ? matrixKey : ''">
<input type="hidden" name="pricing_matrix_explicit" :value="explicitMatrix ? '1' : '0'">
<input type="hidden" name="unit_price_override" :value="sellEdited ? '1' : '0'">

<div class="ops-line-entry-panel ops-line-entry-panel--part space-y-3">
    <label class="ops-field">
        <span class="ops-field-label">Part description</span>
        <input
            name="description"
            value="{{ $description ?? '' }}"
            required
            :placeholder="descriptionPlaceholder()"
            class="ops-field-input"
        >
        <span class="mt-1 block text-[11px] text-slate-500">Internal / ordering identity (brand, catalog name, part #).</span>
    </label>

    @if (\App\Ark\Operations\Settings\ShopSettings::current()->customerPartAllowDescriptionOverride())
        <label class="ops-field">
            <span class="ops-field-label">Customer description</span>
            <input
                name="customer_description"
                value="{{ $customerDescription ?? '' }}"
                placeholder="Optional — clean label for estimate / PDF"
                class="ops-field-input"
            >
            <span class="mt-1 block text-[11px] text-slate-500">Shown on customer documents. Leave blank to use the shop presentation mode.</span>
        </label>
    @endif

    <div class="ops-part-line-pricing">
        <p class="ops-part-line-pricing-heading">Pricing</p>
        <div class="ops-part-line-pricing-grid">
            <label class="ops-field">
                <span class="ops-field-label">Your cost</span>
                <input
                    name="part_cost"
                    x-model="cost"
                    @input="onCostInput()"
                    @blur="onCostBlur()"
                    inputmode="decimal"
                    placeholder="0.00"
                    class="ops-field-input ops-field-input--numeric"
                >
            </label>
            <label class="ops-field">
                <span class="ops-field-label">Parts matrix</span>
                <select
                    x-model="pricingSelection"
                    @change="onPricingSelectionChange($event)"
                    @input="onPricingSelectionChange($event)"
                    class="ops-field-input"
                >
                    @foreach ($partsMatrices as $matrix)
                        <option value="{{ $matrix['key'] }}">{{ $matrix['name'] }}</option>
                    @endforeach
                    <option value="manual">Manual price</option>
                </select>
            </label>
            <label class="ops-field">
                <span class="ops-field-label" x-text="pricingMode === 'matrix' && ! sellEdited ? 'Sell (from matrix)' : 'Sell price'"></span>
                <input
                    name="unit_price"
                    x-model="sell"
                    @input="onSellInput()"
                    :required="pricingMode === 'manual'"
                    inputmode="decimal"
                    placeholder="0.00"
                    :class="pricingMode === 'matrix' && ! sellEdited && sell ? 'ops-field-input ops-field-input--numeric ops-field-input--derived' : 'ops-field-input ops-field-input--numeric'"
                >
            </label>
            <label class="ops-field">
                <span class="ops-field-label">Quantity</span>
                <input
                    name="quantity"
                    value="{{ $quantity ?? '1.00' }}"
                    required
                    inputmode="decimal"
                    placeholder="1"
                    class="ops-field-input ops-field-input--numeric"
                >
            </label>
        </div>
    </div>

    <div class="ops-part-line-sourcing">
        <p class="ops-part-line-pricing-heading">Sourcing</p>
        <div class="grid gap-2 sm:grid-cols-2">
            <label class="ops-field">
                <span class="ops-field-label">Vendor</span>
                <input name="vendor_name" value="{{ $vendorName ?? '' }}" placeholder="Worldpac, NAPA, dealer…" class="ops-field-input">
            </label>
            <label class="ops-field">
                <span class="ops-field-label">Part number</span>
                <input name="part_number" value="{{ $partNumber ?? '' }}" placeholder="Manufacturer or catalog #" class="ops-field-input">
            </label>
            <label class="ops-field sm:col-span-2">
                <span class="ops-field-label">Order note</span>
                <input name="sourcing_notes" value="{{ $sourcingNotes ?? '' }}" placeholder="ETA, source, backorder, will-call…" class="ops-field-input">
            </label>
            <div class="sm:col-span-2">
                @include('operations.repair-orders.partials.repair-order-part-line-metadata-fields', [
                    'partSource' => $partSource ?? null,
                    'partClassification' => $partClassification ?? null,
                    'partWarrantyImpact' => $partWarrantyImpact ?? null,
                ])
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 sm:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="hidden" name="has_core" value="0">
                    <input type="checkbox" name="has_core" value="1" @checked((bool) ($hasCore ?? false)) @change="onCoreChargeToggle()" class="rounded-sm border-slate-300 text-slate-900">
                    Core charge
                </label>
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="hidden" name="save_old_part" value="0">
                    <input type="checkbox" name="save_old_part" value="1" @checked((bool) (($saveOldPart ?? false) || ($hasCore ?? false))) @change="onSaveOldPartToggle($event)" class="rounded-sm border-slate-300 text-slate-900">
                    Save old part
                </label>
            </div>
        </div>
    </div>

    <p class="ops-part-line-guidance">
        <span x-text="suggestionText() || 'Enter your cost to load matrix sell guidance from the server.'"></span>
        <template x-if="marginPercentage">
            <span> · Margin <span x-text="marginPercentage"></span>%</span>
        </template>
        <template x-if="markupPercentage">
            <span> · Markup <span x-text="markupPercentage"></span>%</span>
        </template>
        <template x-if="previewing">
            <span> · Checking server price…</span>
        </template>
        @unless (\App\Ark\Runtime\Authorization\PricingAuthority::allows(auth()->user()))
            <span> · Pricing override authority required for manual sell changes.</span>
        @endunless
    </p>

    @if ($showSubmit ?? true)
        <div class="ops-line-entry-actions">
            <button type="submit" class="ops-line-add-btn ops-line-add-btn--part">
                {{ $submitLabel ?? 'Add Part' }}
            </button>
        </div>
    @endif
</div>
