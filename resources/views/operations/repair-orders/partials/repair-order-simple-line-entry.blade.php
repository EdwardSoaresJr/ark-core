{{-- Labor, fee, note, sublet line entry - expects parent form with arkPartPricing x-data --}}
@php
    $suppressLaborDescription = (bool) ($suppressLaborDescription ?? false);
    $repairTitle = (string) ($repairTitle ?? '');
    $laborMemoryUrl = (string) ($laborMemoryUrl ?? '');
    $hasLaborMemory = $laborMemoryUrl !== '';
    $laborMemoryConfig = [
        'suggestUrl' => $laborMemoryUrl,
        'eventUrl' => route('operations.shop-memory.suggestion-events.store'),
        'repairOrderId' => isset($repairOrder) ? $repairOrder->id : null,
        'surface' => 'labor_entry',
    ];
@endphp
<div class="d-flex flex-column gap-3" data-line-entry-simple :data-line-entry-type="type">
    @if ($suppressLaborDescription)
        <template x-if="type === 'labor'">
            <div>
                <input type="hidden" name="description" value="{{ $repairTitle }}">
                <p class="form-hint mb-0">Labor uses the repair description above. Hours and rate below.</p>
            </div>
        </template>
        <template x-if="type === 'note'">
            <label class="d-block">
                <span class="form-label" x-text="descriptionFieldLabel()"></span>
                <textarea
                    name="description"
                    rows="4"
                    required
                    :placeholder="descriptionPlaceholder()"
                    class="form-control"
                >{{ $description ?? '' }}</textarea>
            </label>
        </template>
        <template x-if="type !== 'labor' && type !== 'note'">
            <label class="d-block">
                <span class="form-label" x-text="descriptionFieldLabel()"></span>
                <input
                    name="description"
                    value="{{ $description ?? '' }}"
                    required
                    :placeholder="descriptionPlaceholder()"
                    class="form-control"
                >
            </label>
        </template>
    @else
        <label class="d-block">
            <span class="form-label" x-text="descriptionFieldLabel()"></span>
            <template x-if="type === 'note'">
                <textarea
                    name="description"
                    rows="4"
                    required
                    :placeholder="descriptionPlaceholder()"
                    class="form-control"
                >{{ $description ?? '' }}</textarea>
            </template>
            @if ($hasLaborMemory)
                <template x-if="type === 'labor'">
                    <div
                        class="position-relative"
                        data-labor-memory
                        x-data="arkLaborMemorySuggest(@js($laborMemoryConfig), @js((string) ($description ?? '')))"
                    >
                        <input
                            name="description"
                            x-model="description"
                            x-ref="descriptionInput"
                            required
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Labor description"
                            class="form-control"
                            @input.debounce.150ms="handleInput()"
                            @keydown="handleKeydown($event)"
                            @focus="handleFocus()"
                            @blur="handleBlur()"
                        >
                        <div
                            class="dropdown-menu w-100 top-100 start-0 mt-1"
                            :class="{ show: suggestionsOpen && hasMatches }"
                            @click.outside="closeSuggestions()"
                            role="listbox"
                            aria-label="Shop labor language"
                        >
                            <template x-for="(row, index) in suggestions" :key="row.id || row.text">
                                <div role="option" :aria-selected="index === activeIndex">
                                    <button
                                        type="button"
                                        class="dropdown-item text-wrap"
                                        :class="{ active: index === activeIndex }"
                                        @mousedown.prevent="chooseSuggestion(row)"
                                    >
                                        <span x-text="row.text"></span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="type !== 'note' && type !== 'labor'">
                    <input
                        name="description"
                        value="{{ $description ?? '' }}"
                        required
                        :placeholder="descriptionPlaceholder()"
                        class="form-control"
                    >
                </template>
            @else
                <template x-if="type !== 'note'">
                    <input
                        name="description"
                        value="{{ $description ?? '' }}"
                        required
                        :placeholder="descriptionPlaceholder()"
                        class="form-control"
                    >
                </template>
            @endif
        </label>
    @endif

    <template x-if="type !== 'note' && type === 'labor'">
        @include('operations.repair-orders.partials.repair-order-labor-authority-fields', [
            'laborCategories' => $laborCategories ?? [],
        ])
    </template>

    <template x-if="type === 'sublet'">
        <fieldset class="form-fieldset mb-0">
            <legend class="subheader mb-2" x-text="pricingHeading()"></legend>
            <div class="row g-2">
                <label class="col-6 col-md-3">
                    <span class="form-label">Vendor cost</span>
                    <input
                        name="part_cost"
                        x-model="cost"
                        @input="onSubletCostInput()"
                        @blur="onCostBlur()"
                        inputmode="decimal"
                        :placeholder="costPlaceholder()"
                        class="form-control text-end ark-money"
                    >
                </label>
                <label class="col-6 col-md-3">
                    <span class="form-label">Markup %</span>
                    <input
                        x-model="markup"
                        @input="onSubletMarkupInput()"
                        inputmode="decimal"
                        placeholder="e.g. 15"
                        class="form-control text-end ark-money"
                    >
                </label>
                <label class="col-6 col-md-3">
                    <span class="form-label">Sell price</span>
                    <input
                        name="unit_price"
                        x-model="sell"
                        @input="onSellInput()"
                        required
                        inputmode="decimal"
                        :placeholder="sellPlaceholder()"
                        class="form-control text-end ark-money"
                    >
                </label>
                <label class="col-6 col-md-3">
                    <span class="form-label" x-text="quantityFieldLabel()"></span>
                    <input
                        name="quantity"
                        value="{{ $quantity ?? '1.00' }}"
                        required
                        inputmode="decimal"
                        :placeholder="quantityPlaceholder()"
                        class="form-control text-end ark-money"
                    >
                </label>
            </div>
            <p class="form-hint mt-2 mb-0">
                <span x-text="subletPricingGuidance()"></span>
                <template x-if="marginPercentage">
                    <span> · Margin <span x-text="marginPercentage"></span>%</span>
                </template>
                <template x-if="markupPercentage">
                    <span> · Markup <span x-text="markupPercentage"></span>%</span>
                </template>
                <template x-if="previewing">
                    <span> · Checking price…</span>
                </template>
            </p>
        </fieldset>
    </template>

    <template x-if="type !== 'note' && type !== 'labor' && type !== 'sublet'">
        <fieldset class="form-fieldset mb-0">
            <legend class="subheader mb-2" x-text="pricingHeading()"></legend>
            <div class="row g-2">
                <label class="col-6">
                    <span class="form-label" x-text="sellFieldLabel()"></span>
                    <input
                        name="unit_price"
                        x-model="sell"
                        @input="onSellInput()"
                        required
                        inputmode="decimal"
                        :placeholder="sellPlaceholder()"
                        class="form-control text-end ark-money"
                    >
                </label>
                <label class="col-6">
                    <span class="form-label" x-text="quantityFieldLabel()"></span>
                    <input
                        name="quantity"
                        value="{{ $quantity ?? '1.00' }}"
                        required
                        inputmode="decimal"
                        :placeholder="quantityPlaceholder()"
                        class="form-control text-end ark-money"
                    >
                </label>
            </div>
        </fieldset>
    </template>

    <template x-if="type === 'note'">
        <div>
            <input type="hidden" name="type" value="note">
            <input type="hidden" name="unit_price" value="0">
            <input type="hidden" name="quantity" value="1">
            @php
                $noteDefaults = \App\Ark\Operations\RepairOrders\NoteAudience::defaultsFromShop();
            @endphp
            @include('operations.repair-orders.partials.repair-order-note-privacy-field', [
                'audience' => [
                    'advisor' => $noteDefaults->advisor,
                    'technician' => $noteDefaults->technician,
                    'customer' => $noteDefaults->customer,
                ],
                'ui' => 'tabler',
            ])
        </div>
    </template>

    <p x-show="simpleLineGuidance()" class="form-hint mb-0" x-text="simpleLineGuidance()"></p>

    @if ($showSubmit ?? true)
        <div class="d-flex justify-content-end">
            <button
                type="submit"
                class="btn btn-primary"
                data-line-add
                x-text="addLineButtonLabel()"
            ></button>
        </div>
    @endif
</div>
