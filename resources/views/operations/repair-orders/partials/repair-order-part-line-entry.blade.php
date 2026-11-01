{{-- Part line entry - expects parent form with arkPartPricing x-data --}}
<input type="hidden" name="pricing_mode" :value="pricingMode">
<input type="hidden" name="pricing_matrix_key" :value="pricingMode === 'matrix' ? matrixKey : ''">
<input type="hidden" name="pricing_matrix_explicit" :value="explicitMatrix ? '1' : '0'">
<input type="hidden" name="unit_price_override" :value="sellEdited ? '1' : '0'">

<div class="d-flex flex-column gap-3" data-line-entry-part>
    <label class="d-block">
        <span class="form-label">Part description</span>
        <input
            name="description"
            value="{{ $description ?? '' }}"
            required
            :placeholder="descriptionPlaceholder()"
            class="form-control"
        >
        <span class="form-hint">Internal / ordering identity (brand, catalog name, part #).</span>
    </label>

    @if (\App\Ark\Operations\Settings\ShopSettings::current()->customerPartAllowDescriptionOverride())
        <label class="d-block">
            <span class="form-label">Customer description</span>
            <input
                name="customer_description"
                value="{{ $customerDescription ?? '' }}"
                placeholder="Optional - clean label for estimate / PDF"
                class="form-control"
            >
            <span class="form-hint">Shown on customer documents. Leave blank to use the shop presentation mode.</span>
        </label>
    @endif

    <fieldset class="form-fieldset mb-0">
        <legend class="subheader mb-2">Pricing</legend>
        <div class="row g-2">
            <label class="col-6 col-md-3">
                <span class="form-label">Your cost</span>
                <input
                    name="part_cost"
                    x-model="cost"
                    @input="onCostInput()"
                    @blur="onCostBlur()"
                    inputmode="decimal"
                    placeholder="0.00"
                    class="form-control text-end ark-money"
                >
            </label>
            <label class="col-6 col-md-3">
                <span class="form-label">Parts matrix</span>
                <select
                    x-model="pricingSelection"
                    @change="onPricingSelectionChange($event)"
                    @input="onPricingSelectionChange($event)"
                    class="form-select"
                >
                    @foreach ($partsMatrices as $matrix)
                        <option value="{{ $matrix['key'] }}">{{ $matrix['name'] }}</option>
                    @endforeach
                    <option value="manual">Manual price</option>
                </select>
            </label>
            <label class="col-6 col-md-3">
                <span class="form-label" x-text="pricingMode === 'matrix' && ! sellEdited ? 'Sell (from matrix)' : 'Sell price'"></span>
                <input
                    name="unit_price"
                    x-model="sell"
                    @input="onSellInput()"
                    :required="pricingMode === 'manual'"
                    inputmode="decimal"
                    placeholder="0.00"
                    class="form-control text-end ark-money"
                    :class="pricingMode === 'matrix' && ! sellEdited && sell ? 'bg-secondary-lt' : ''"
                >
            </label>
            <label class="col-6 col-md-3">
                <span class="form-label">Quantity</span>
                <input
                    name="quantity"
                    value="{{ $quantity ?? '1.00' }}"
                    required
                    inputmode="decimal"
                    placeholder="1"
                    class="form-control text-end ark-money"
                >
            </label>
        </div>
    </fieldset>

    <fieldset class="form-fieldset mb-0">
        <legend class="subheader mb-2">Sourcing</legend>
        <div class="row g-2">
            <label class="col-sm-6">
                <span class="form-label">Vendor</span>
                <input name="vendor_name" value="{{ $vendorName ?? '' }}" placeholder="Worldpac, NAPA, dealer…" class="form-control">
            </label>
            <label class="col-sm-6">
                <span class="form-label">Part number</span>
                <input name="part_number" value="{{ $partNumber ?? '' }}" placeholder="Manufacturer or catalog #" class="form-control">
            </label>
            <label class="col-12">
                <span class="form-label">Order note</span>
                <input name="sourcing_notes" value="{{ $sourcingNotes ?? '' }}" placeholder="ETA, source, backorder, will-call…" class="form-control">
            </label>
            <div class="col-12">
                @include('operations.repair-orders.partials.repair-order-part-line-metadata-fields', [
                    'partSource' => $partSource ?? null,
                    'partClassification' => $partClassification ?? null,
                    'partWarrantyImpact' => $partWarrantyImpact ?? null,
                ])
            </div>
            <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                <label class="form-check mb-0">
                    <input type="hidden" name="has_core" value="0">
                    <input type="checkbox" name="has_core" value="1" @checked((bool) ($hasCore ?? false)) @change="onCoreChargeToggle()" class="form-check-input">
                    <span class="form-check-label">Core charge</span>
                </label>
                <label class="form-check mb-0">
                    <input type="hidden" name="save_old_part" value="0">
                    <input type="checkbox" name="save_old_part" value="1" @checked((bool) (($saveOldPart ?? false) || ($hasCore ?? false))) @change="onSaveOldPartToggle($event)" class="form-check-input">
                    <span class="form-check-label">Save old part</span>
                </label>
            </div>
        </div>
    </fieldset>

    <p class="form-hint mb-0">
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
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary" data-line-add>
                {{ $submitLabel ?? 'Add Part' }}
            </button>
        </div>
    @endif
</div>
