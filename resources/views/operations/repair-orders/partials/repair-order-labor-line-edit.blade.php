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

<div id="line-editor-{{ $line->id }}" @class([
    'scroll-mt-24',
    'card card-sm' => ! $hideInlineChrome,
])>
    <div @class(['card-body' => ! $hideInlineChrome])>
    @unless ($hideInlineChrome)
        <div class="d-flex align-items-center justify-content-between gap-3 small mb-2">
            <span class="fw-medium text-secondary">Editing labor</span>
            <a
                href="{{ $cancelUrl }}"
                data-refresh-scope="worksheet"
                data-continuity-focus="#line-{{ $line->id }} a[href*='editing_line']"
                @click.prevent="editLine($event)"
                class="btn btn-ghost-secondary btn-icon btn-sm"
                aria-label="Cancel editing"
                title="Cancel editing"
            >
                <x-tabler.icon name="x" />
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
        class="d-flex flex-column gap-2"
    >
        <form
            id="line-update-{{ $line->id }}"
            method="POST"
            action="{{ route('operations.repair-orders.lines.update', [$repairOrder, $line]) }}"
            data-worksheet-continuity
            data-refresh-scope="worksheet"
            data-continuity-focus="#line-{{ $line->id }}"
            @if ($workspaceModalForm) data-workspace-modal-form="{{ $workspaceModalForm }}" @endif
            @submit.prevent="submitLine($event)"
            class="d-flex flex-column gap-2"
        >
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <input type="hidden" name="repair_order_concern_id" value="{{ $line->repair_order_concern_id }}">
            <input type="hidden" name="repair_order_work_group_id" value="{{ $line->repair_order_work_group_id }}">
            <input type="hidden" name="type" value="labor">

            @if ($suppressLaborDescription)
                <div class="card card-sm bg-secondary-lt">
                    <div class="card-body">
                        <div class="subheader">Labor</div>
                        <div class="fw-bold">{{ $compactSummary }}</div>
                        <div class="small text-secondary">Matches the repair above. Description stays available under Advanced.</div>
                    </div>
                </div>
                <template x-if="! laborDescriptionExpanded">
                    <input type="hidden" name="description" value="{{ $line->description }}">
                </template>
                <template x-if="laborDescriptionExpanded">
                    <div
                        class="border-top pt-2"
                        x-data="arkLaborMemorySuggest(@js($laborMemoryConfig), @js($line->description))"
                    >
                        <label class="d-block">
                            <span class="form-label">Labor description</span>
                            <div class="position-relative">
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
                        </label>
                    </div>
                </template>
                <button
                    type="button"
                    class="btn btn-link btn-sm p-0 align-self-start"
                    @click="laborDescriptionExpanded = ! laborDescriptionExpanded; if (laborDescriptionExpanded) { laborAdjustExpanded = true }"
                    :aria-expanded="laborDescriptionExpanded"
                    x-text="laborDescriptionExpanded ? 'Hide advanced labor' : 'Advanced · Edit labor description'"
                ></button>
            @else
                <div
                    x-data="arkLaborMemorySuggest(@js($laborMemoryConfig), @js($line->description))"
                >
                    <label class="d-block">
                        <span class="form-label">Labor description</span>
                        <div class="position-relative">
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
</div>
