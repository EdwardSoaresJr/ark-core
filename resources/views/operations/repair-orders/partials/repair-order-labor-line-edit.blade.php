@php
    use App\Ark\Operations\Labor\LaborAdjustmentReason;

    $laborReasonKey = '';
    $laborReasonCustom = '';

    if (filled($line->labor_adjustment_reason)) {
        $matchedReason = collect(LaborAdjustmentReason::cases())
            ->first(fn (LaborAdjustmentReason $reason): bool => $reason->label() === $line->labor_adjustment_reason);

        if ($matchedReason instanceof LaborAdjustmentReason) {
            $laborReasonKey = $matchedReason->value;
        } else {
            $laborReasonKey = LaborAdjustmentReason::Custom->value;
            $laborReasonCustom = $line->labor_adjustment_reason;
        }
    }

    $suppressLaborDescription = (bool) ($suppressLaborDescription ?? false);
    $hideInlineChrome = (bool) ($hideInlineChrome ?? false);
    $workspaceModalForm = $workspaceModalForm ?? null;
    $laborNeedsAdvanced = ($line->labor_adjustment ?? 'normal') !== 'normal'
        || (bool) $line->labor_hours_overridden
        || filled($line->labor_override_reason);
    $savedLaborCategoryKey = $line->labor_category_key ?? $defaultLaborCategoryKey;
    $policyResolvedRateCents = $line->policy_resolved_labor_rate_cents !== null
        ? (int) $line->policy_resolved_labor_rate_cents
        : null;
    $laborRateOverridden = filled($line->labor_rate_override_reason)
        || ($policyResolvedRateCents !== null && (int) $line->unit_price_cents !== $policyResolvedRateCents);
    $compactSummary = \App\Ark\Operations\RepairOrders\LaborDescriptionPresentation::compactLaborSummary($line);
    $laborMemoryConfig = [
        'suggestUrl' => route('operations.repair-orders.labor-memory-suggest', $repairOrder),
        'eventUrl' => route('operations.shop-memory.suggestion-events.store'),
        'repairOrderId' => $repairOrder->id,
        'surface' => 'labor_entry',
    ];
@endphp

<div id="line-{{ $line->id }}" @class([
    'scroll-mt-24',
    'border border-slate-300 bg-white px-3 py-2 ring-1 ring-slate-100' => ! $hideInlineChrome,
])>
    @unless ($hideInlineChrome)
        <div class="mb-2 flex items-center justify-between gap-3 text-xs">
            <span class="font-semibold text-slate-500">Editing labor</span>
            <a
                href="{{ $cancelUrl }}"
                data-refresh-scope="worksheet"
                data-continuity-focus="#line-{{ $line->id }} a[href*='editing_line']"
                @click.prevent="editLine($event)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-sm text-slate-500 hover:bg-slate-50 hover:text-slate-900"
                aria-label="Cancel editing"
                title="Cancel editing"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="none">
                    <path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
            </a>
        </div>
    @endunless

    <div
        x-data="arkPartPricing({
            type: 'labor',
            concernSummary: @js($concern->summary),
            sell: '{{ $totals->decimal($line->unit_price_cents) }}',
            sellEdited: @js($laborRateOverridden),
            defaultLaborRate: '{{ $defaultLaborRate }}',
            laborCategories: @js($laborCategories),
            defaultLaborCategoryKey: @js($defaultLaborCategoryKey),
            laborCategoryKey: @js($savedLaborCategoryKey),
            laborEnteredHours: @js((string) ($line->labor_entered_hours ?? $line->quantity)),
            laborAdjustment: @js($line->labor_adjustment ?? 'normal'),
            laborCustomFactor: @js((string) ($line->labor_adjustment_factor ?? '1.25')),
            laborReason: @js($laborReasonKey),
            laborReasonCustom: @js($laborReasonCustom),
            laborHoursOverridden: @js((bool) $line->labor_hours_overridden),
            laborFinalHours: @js((string) $line->quantity),
            laborOverrideReason: @js($line->labor_override_reason ?? ''),
            laborRateOverrideReason: @js($line->labor_rate_override_reason ?? ''),
            laborAdjustExpanded: @js($laborNeedsAdvanced),
            laborDescriptionExpanded: false,
        }, partsMatrices, defaultPartsMatrixKey, '{{ route('operations.repair-orders.lines.pricing-preview', $repairOrder) }}')"
        class="space-y-2"
    >
        <form
            id="line-update-{{ $line->id }}"
            method="POST"
            action="{{ route('operations.repair-orders.lines.update', [$repairOrder, $line]) }}"
            data-refresh-scope="worksheet"
            data-continuity-focus="#line-{{ $line->id }}"
            @if ($workspaceModalForm) data-workspace-modal-form="{{ $workspaceModalForm }}" @endif
            @submit.prevent="submitLine($event)"
            class="space-y-2"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <input type="hidden" name="repair_order_concern_id" value="{{ $line->repair_order_concern_id }}">
            <input type="hidden" name="repair_order_work_group_id" value="{{ $line->repair_order_work_group_id }}">
            <input type="hidden" name="type" value="labor">

            @if ($suppressLaborDescription)
                <div class="rounded-sm border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Labor</p>
                    <p class="mt-0.5 text-sm font-semibold text-slate-950">{{ $compactSummary }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">Matches the repair above. Description stays available under Advanced.</p>
                </div>
                <template x-if="! laborDescriptionExpanded">
                    <input type="hidden" name="description" value="{{ $line->description }}">
                </template>
                <template x-if="laborDescriptionExpanded">
                    <div
                        class="space-y-2 border-t border-slate-200 pt-2"
                        x-data="arkLaborMemorySuggest(@js($laborMemoryConfig), @js($line->description))"
                    >
                        <label class="block text-[11px] font-medium text-slate-500">
                            Labor description
                            <div class="ops-labor-memory__input-wrap mt-1">
                                <input
                                    name="description"
                                    x-model="description"
                                    x-ref="descriptionInput"
                                    required
                                    autocomplete="off"
                                    spellcheck="false"
                                    placeholder="Labor description"
                                    class="w-full rounded-sm border border-slate-300 px-3 py-1.5 text-sm"
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
                        </label>
                    </div>
                </template>
                <button
                    type="button"
                    class="text-[11px] font-semibold text-slate-600 underline underline-offset-2 hover:text-slate-900"
                    @click="laborDescriptionExpanded = ! laborDescriptionExpanded; if (laborDescriptionExpanded) { laborAdjustExpanded = true }"
                    x-text="laborDescriptionExpanded ? 'Hide advanced labor' : 'Advanced · Edit labor description'"
                ></button>
            @else
                <div
                    x-data="arkLaborMemorySuggest(@js($laborMemoryConfig), @js($line->description))"
                >
                    <label class="block text-[11px] font-medium text-slate-500">
                        Labor description
                        <div class="ops-labor-memory__input-wrap mt-1">
                            <input
                                name="description"
                                x-model="description"
                                x-ref="descriptionInput"
                                required
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="Labor description"
                                class="w-full rounded-sm border border-slate-300 px-3 py-1.5 text-sm"
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
                    </label>
                </div>
            @endif

            @include('operations.repair-orders.partials.repair-order-labor-authority-fields', [
                'laborCategories' => $laborCategories,
                'selectedLaborCategoryKey' => $savedLaborCategoryKey,
            ])
        </form>

        @unless ($hideInlineChrome)
            @include('operations.repair-orders.partials.repair-order-line-edit-actions', [
                'line' => $line,
                'repairOrder' => $repairOrder,
                'estimateVersion' => $estimateVersion,
                'gridPlacement' => false,
            ])
        @endunless
    </div>
</div>
