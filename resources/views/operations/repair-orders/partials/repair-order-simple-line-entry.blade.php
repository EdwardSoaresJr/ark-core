{{-- Labor, fee, note, sublet line entry — expects parent form with arkPartPricing x-data --}}
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
<div
    class="ops-line-entry-panel space-y-3"
    :class="{
        'ops-line-entry-panel--labor': type === 'labor',
        'ops-line-entry-panel--note': type === 'note',
        'ops-line-entry-panel--fee': type === 'fee',
        'ops-line-entry-panel--sublet': type === 'sublet',
    }"
>
    @if ($suppressLaborDescription)
        <template x-if="type === 'labor'">
            <div class="space-y-1">
                <input type="hidden" name="description" value="{{ $repairTitle }}">
                <p class="text-[11px] text-slate-500">Labor uses the repair description above. Hours and rate below.</p>
            </div>
        </template>
        <template x-if="type === 'note'">
            <label class="ops-field">
                <span class="ops-field-label" x-text="descriptionFieldLabel()"></span>
                <textarea
                    name="description"
                    rows="4"
                    required
                    :placeholder="descriptionPlaceholder()"
                    class="ops-field-input ops-field-input--note ops-field-textarea--note"
                >{{ $description ?? '' }}</textarea>
            </label>
        </template>
        <template x-if="type !== 'labor' && type !== 'note'">
            <label class="ops-field">
                <span class="ops-field-label" x-text="descriptionFieldLabel()"></span>
                <input
                    name="description"
                    value="{{ $description ?? '' }}"
                    required
                    :placeholder="descriptionPlaceholder()"
                    class="ops-field-input"
                >
            </label>
        </template>
    @else
        <label class="ops-field">
            <span class="ops-field-label" x-text="descriptionFieldLabel()"></span>
            <template x-if="type === 'note'">
                <textarea
                    name="description"
                    rows="4"
                    required
                    :placeholder="descriptionPlaceholder()"
                    class="ops-field-input ops-field-input--note ops-field-textarea--note"
                >{{ $description ?? '' }}</textarea>
            </template>
            @if ($hasLaborMemory)
                <template x-if="type === 'labor'">
                    <div
                        class="ops-labor-memory"
                        x-data="arkLaborMemorySuggest(@js($laborMemoryConfig), @js((string) ($description ?? '')))"
                    >
                        <div class="ops-labor-memory__input-wrap">
                            <input
                                name="description"
                                x-model="description"
                                x-ref="descriptionInput"
                                required
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="Labor description"
                                class="ops-field-input"
                                @input.debounce.150ms="handleInput()"
                                @keydown="handleKeydown($event)"
                                @focus="handleFocus()"
                                @blur="handleBlur()"
                            >
                            <div
                                class="ops-labor-memory__suggestions"
                                x-show="suggestionsOpen && hasMatches"
                                @click.outside="closeSuggestions()"
                                role="listbox"
                                aria-label="Shop labor language"
                            >
                                <ul class="ops-labor-memory__list">
                                    <template x-for="(row, index) in suggestions" :key="row.id || row.text">
                                        <li role="option" :aria-selected="index === activeIndex">
                                            <button
                                                type="button"
                                                class="ops-labor-memory__suggestion"
                                                :class="{ 'ops-labor-memory__suggestion--active': index === activeIndex }"
                                                @mousedown.prevent="chooseSuggestion(row)"
                                            >
                                                <span x-text="row.text"></span>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                </template>
                <template x-if="type !== 'note' && type !== 'labor'">
                    <input
                        name="description"
                        value="{{ $description ?? '' }}"
                        required
                        :placeholder="descriptionPlaceholder()"
                        class="ops-field-input"
                    >
                </template>
            @else
                <template x-if="type !== 'note'">
                    <input
                        name="description"
                        value="{{ $description ?? '' }}"
                        required
                        :placeholder="descriptionPlaceholder()"
                        class="ops-field-input"
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
        <div class="ops-part-line-pricing">
            <p class="ops-part-line-pricing-heading" x-text="pricingHeading()"></p>
            <div class="ops-part-line-pricing-grid">
                <label class="ops-field">
                    <span class="ops-field-label">Vendor cost</span>
                    <input
                        name="part_cost"
                        x-model="cost"
                        @input="onSubletCostInput()"
                        @blur="onCostBlur()"
                        inputmode="decimal"
                        :placeholder="costPlaceholder()"
                        class="ops-field-input ops-field-input--numeric"
                    >
                </label>
                <label class="ops-field">
                    <span class="ops-field-label">Markup %</span>
                    <input
                        x-model="markup"
                        @input="onSubletMarkupInput()"
                        inputmode="decimal"
                        placeholder="e.g. 15"
                        class="ops-field-input ops-field-input--numeric"
                    >
                </label>
                <label class="ops-field">
                    <span class="ops-field-label">Sell price</span>
                    <input
                        name="unit_price"
                        x-model="sell"
                        @input="onSellInput()"
                        required
                        inputmode="decimal"
                        :placeholder="sellPlaceholder()"
                        class="ops-field-input ops-field-input--numeric"
                    >
                </label>
                <label class="ops-field">
                    <span class="ops-field-label" x-text="quantityFieldLabel()"></span>
                    <input
                        name="quantity"
                        value="{{ $quantity ?? '1.00' }}"
                        required
                        inputmode="decimal"
                        :placeholder="quantityPlaceholder()"
                        class="ops-field-input ops-field-input--numeric"
                    >
                </label>
            </div>
            <p class="ops-part-line-guidance">
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
        </div>
    </template>

    <template x-if="type !== 'note' && type !== 'labor' && type !== 'sublet'">
        <div class="ops-part-line-pricing">
            <p class="ops-part-line-pricing-heading" x-text="pricingHeading()"></p>
            <div class="ops-line-entry-pricing-grid ops-line-entry-pricing-grid--pair">
                <label class="ops-field">
                    <span class="ops-field-label" x-text="sellFieldLabel()"></span>
                    <input
                        name="unit_price"
                        x-model="sell"
                        @input="onSellInput()"
                        required
                        inputmode="decimal"
                        :placeholder="sellPlaceholder()"
                        class="ops-field-input ops-field-input--numeric"
                    >
                </label>
                <label class="ops-field">
                    <span class="ops-field-label" x-text="quantityFieldLabel()"></span>
                    <input
                        name="quantity"
                        value="{{ $quantity ?? '1.00' }}"
                        required
                        inputmode="decimal"
                        :placeholder="quantityPlaceholder()"
                        class="ops-field-input ops-field-input--numeric"
                    >
                </label>
            </div>
        </div>
    </template>

    <template x-if="type === 'note'">
        <div class="space-y-2">
            <input type="hidden" name="type" value="note">
            <input type="hidden" name="unit_price" value="0">
            <input type="hidden" name="quantity" value="1">
            @include('operations.repair-orders.partials.repair-order-note-privacy-field', [
                'audience' => [
                    'advisor' => true,
                    'technician' => false,
                    'customer' => ! (bool) ($defaultNotesPrivate ?? true),
                ],
            ])
        </div>
    </template>

    <p x-show="simpleLineGuidance()" class="ops-simple-line-guidance" x-text="simpleLineGuidance()"></p>

    <div class="ops-line-entry-actions">
        <button
            type="submit"
            class="ops-line-add-btn"
            :class="type ? `ops-line-add-btn--${type}` : ''"
            x-text="addLineButtonLabel()"
        ></button>
    </div>
</div>
