@php
    use App\Ark\Operations\RepairOrders\ScopeEntryKind;
    use App\Ark\ShopMemory\ShopMemoryFeatures;
    use App\Ark\ShopMemory\ShopMemoryProviderCatalog;

    $isTerminal = $isTerminal ?? false;
    $editingLineId = $editingLineId ?? null;
    $engineOilServices = $engineOilServices ?? collect();
    $testingAuthorizations = $testingAuthorizations ?? collect();
    $evidenceGallery = $evidenceGallery ?? ['items' => collect(), 'concerns' => collect()];
    $evidenceItems = $evidenceGallery['items'] ?? collect();
    $customerDocuments = $customerDocuments ?? collect();
    $attachableDocuments = $attachableDocuments ?? collect();
    $evidenceConcerns = $evidenceGallery['concerns'] ?? collect();

    $defaultEntryKind = ScopeEntryKind::CustomerRequested;
    $problemLanguageEnabled = ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::HISTORICAL_CONCERN)
        || ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::TECHNICIAN_OBSERVATION)
        || ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::INSPECTION_FINDING)
        || ShopMemoryFeatures::providerEnabled(ShopMemoryProviderCatalog::CUSTOMER_INTAKE);
    $aiRewriteEnabled = ShopMemoryFeatures::aiRewriteEnabled();
    $vocabularyUrl = route('operations.repair-orders.concerns.vocabulary-suggest', $repairOrder);
    $concernMemoryUrl = route('operations.repair-orders.concern-memory-suggest', $repairOrder);
    $intakeConfig = [
        'suggestUrl' => $problemLanguageEnabled ? $concernMemoryUrl : $vocabularyUrl,
        'suggestMode' => $problemLanguageEnabled ? 'shop_memory' : 'vocabulary',
        'defaultEntryKind' => $defaultEntryKind->value,
        'eventUrl' => route('operations.shop-memory.suggestion-events.store'),
        'rewriteUrl' => route('operations.repair-orders.ai-rewrite', $repairOrder),
        'aiRewriteEnabled' => $aiRewriteEnabled,
        'repairOrderId' => $repairOrder->id,
        'surface' => 'add_concern',
        'priorVisits' => $priorVisitMentions['suggestions'] ?? [],
    ];

    $initialTask = null;
    $initialContext = [];

    if ($editingLineId) {
        $editingLine = $repairOrder->lines->firstWhere('id', $editingLineId);
        $initialTask = 'edit-line';
        $initialContext = [
            'lineId' => $editingLineId,
            'lineLabel' => $editingLine?->type?->staffLabel() ?? 'Line',
            'lineType' => $editingLine?->type?->value,
        ];
    } elseif ($errors->has('summary') || $errors->has('observed_summary') || $errors->has('scope_entry_kind')) {
        $initialTask = 'concern';
    } elseif (old('type') && filled(old('repair_order_concern_id'))) {
        $type = (string) old('type');
        if (in_array($type, ['labor', 'part', 'sublet', 'note'], true)) {
            $initialTask = $type;
            $initialContext = [
                'lineType' => $type,
                'concernId' => (int) old('repair_order_concern_id'),
            ];
            if (filled(old('repair_order_work_group_id'))) {
                $initialContext['workGroupId'] = (int) old('repair_order_work_group_id');
            }
        }
    } elseif (old('title') && $errors->any()) {
        $initialTask = 'repair-action';
        $initialContext = [
            'concernId' => (int) old('repair_order_concern_id', 0),
        ];
    } elseif ($errors->has('visit_reason')) {
        $initialTask = 'visit-reason';
    } elseif ($errors->has('review_request')) {
        $initialTask = 'review-request';
        $initialContext = [
            'closePaid' => (string) old('close_paid', '') === '1',
        ];
    } elseif ($errors->hasAny(['customer_states', 'verified_findings', 'dtcs_summary', 'recommendation', 'summary'])
        && filled(old('summary'))) {
        $initialTask = 'concern-narrative';
    }
@endphp

{{-- Always host the Workspace Modal on RO show: identity/mileage authoring remains available after close. --}}
    <x-operations.workspace-modal
        :initial-task="$isTerminal ? null : $initialTask"
        :initial-context="$isTerminal ? [] : $initialContext"
    >
        @unless ($isTerminal)
        {{-- Add Work chooser — radio list, not a menu of buttons --}}
        <div class="ops-workspace-modal__panel" x-show="task === 'add-work'" x-cloak>
            <fieldset class="ops-workspace-modal__chooser">
                <legend class="sr-only">What would you like to add?</legend>
                <label class="ops-workspace-modal__chooser-option">
                    <input
                        type="radio"
                        class="ops-workspace-modal__chooser-radio"
                        name="add_work_choice"
                        value="concern"
                        x-model="addWorkChoice"
                    >
                    <span class="ops-workspace-modal__chooser-copy">
                        <span class="ops-workspace-modal__chooser-title">Customer Concern</span>
                        <span class="ops-workspace-modal__chooser-hint">The problem or request the customer brought in.</span>
                    </span>
                </label>
                <label class="ops-workspace-modal__chooser-option">
                    <input
                        type="radio"
                        class="ops-workspace-modal__chooser-radio"
                        name="add_work_choice"
                        value="oil"
                        x-model="addWorkChoice"
                    >
                    <span class="ops-workspace-modal__chooser-copy">
                        <span class="ops-workspace-modal__chooser-title">Engine Oil Service</span>
                        <span class="ops-workspace-modal__chooser-hint">Authorize a maintenance oil package for this vehicle.</span>
                    </span>
                </label>
                <label class="ops-workspace-modal__chooser-option">
                    <input
                        type="radio"
                        class="ops-workspace-modal__chooser-radio"
                        name="add_work_choice"
                        value="testing"
                        x-model="addWorkChoice"
                    >
                    <span class="ops-workspace-modal__chooser-copy">
                        <span class="ops-workspace-modal__chooser-title">Testing Package</span>
                        <span class="ops-workspace-modal__chooser-hint">Authorize diagnostic or inspection testing.</span>
                    </span>
                </label>
                <label class="ops-workspace-modal__chooser-option">
                    <input
                        type="radio"
                        class="ops-workspace-modal__chooser-radio"
                        name="add_work_choice"
                        value="saved-work"
                        x-model="addWorkChoice"
                    >
                    <span class="ops-workspace-modal__chooser-copy">
                        <span class="ops-workspace-modal__chooser-title">Common Job</span>
                        <span class="ops-workspace-modal__chooser-hint">Add a common job from a shop template — labor, parts, and fees in one step.</span>
                    </span>
                </label>
            </fieldset>
        </div>
        @endunless

        @unless ($isTerminal)
        {{-- Concern --}}
        <div class="ops-workspace-modal__panel" x-show="task === 'concern'" x-cloak>
            <div id="concern-store">
                @include('operations.repair-orders.partials.repair-order-estimate-new-scope-form', [
                    'repairOrder' => $repairOrder,
                    'estimateVersion' => $estimateVersion,
                    'intakeConfig' => $intakeConfig,
                    'popupMode' => true,
                    'hideSubmitActions' => true,
                    'workspaceModalForm' => 'concern',
                ])
            </div>
        </div>

        {{-- Line create (one form per repair action for correct pricing defaults) --}}
        @foreach ($repairOrder->concerns as $concern)
            @php
                $concernPartsMatrixKey = $concern->billing_posture
                    ->defaultPartsMatrix($partstechShopSettings ?? \App\Ark\Operations\Settings\ShopSettings::current())['key'];
                $concernLaborDefaults = ($partstechShopSettings ?? \App\Ark\Operations\Settings\ShopSettings::current())
                    ->laborDefaultsForConcern($concern->billing_posture, $repairOrder->customer);
                $concernDefaultLaborRate = $concernLaborDefaults['rate'];
                $concernDefaultLaborCategoryKey = $concernLaborDefaults['category_key'];
                $concernDefaultPartPricingMode = $concern->billing_posture->prefersManualPartPricing() ? 'manual' : 'matrix';
                $concernDefaultPartSell = $concernDefaultPartPricingMode === 'manual' ? '0' : '';
            @endphp
            @foreach ($concern->workGroups as $workGroup)
                @php
                    $allowedComposerTypes = $workGroup->allowedComposerLineTypes();
                    $suppressComposeLaborDescription = ! $workGroup->hasLaborAnchor();
                    $oldBelongs = (string) old('repair_order_work_group_id') === (string) $workGroup->id;
                    $defaultLineType = $oldBelongs ? old('type', '') : '';
                    $defaultLineDescription = $oldBelongs
                        ? old('description', '')
                        : '';
                    $defaultLineSell = $oldBelongs
                        ? old('unit_price', in_array(old('type'), ['part'], true) ? $concernDefaultPartSell : (in_array(old('type'), ['note'], true) ? '0' : $concernDefaultLaborRate))
                        : '';
                    $laborStoreNeedsAdvanced = $oldBelongs && (
                        old('labor_adjustment', 'normal') !== 'normal'
                        || old('labor_category_key', $concernDefaultLaborCategoryKey) !== $concernDefaultLaborCategoryKey
                        || old('labor_hours_overridden')
                        || filled(old('labor_override_reason'))
                    );
                @endphp
                <div
                    class="ops-workspace-modal__panel"
                    x-show="['labor','part','sublet','note'].includes(task) && String(context.workGroupId) === '{{ $workGroup->id }}'"
                    x-cloak
                >
                    <form
                        id="workspace-line-create-{{ $workGroup->id }}"
                        method="POST"
                        action="{{ route('operations.repair-orders.lines.store', $repairOrder) }}"
                        data-workspace-modal-form="line-create"
                        data-refresh-scope="worksheet"
                        data-saving-label="Saving…"
                        @submit.prevent="submitLine($event)"
                        x-data="arkPartPricing({ type: '{{ $defaultLineType }}', concernSummary: @js($concern->summary), pricingMode: '{{ $oldBelongs ? old('pricing_mode', $concernDefaultPartPricingMode) : $concernDefaultPartPricingMode }}', defaultPricingMode: '{{ $concernDefaultPartPricingMode }}', defaultPartSell: '{{ $concernDefaultPartSell }}', matrixKey: '{{ $oldBelongs ? old('pricing_matrix_key') : '' }}', explicitMatrix: {{ $oldBelongs && old('pricing_matrix_explicit') ? 'true' : 'false' }}, cost: '{{ $oldBelongs ? old('part_cost') : '' }}', sell: '{{ $defaultLineSell }}', sellEdited: {{ $oldBelongs && (old('unit_price_override') || old('labor_rate_overridden')) ? 'true' : 'false' }}, defaultLaborRate: '{{ $concernDefaultLaborRate }}', laborCategories: @js($laborCategories), defaultLaborCategoryKey: @js($concernDefaultLaborCategoryKey), laborCategoryKey: @js($oldBelongs ? old('labor_category_key', $concernDefaultLaborCategoryKey) : $concernDefaultLaborCategoryKey), laborEnteredHours: @js($oldBelongs ? old('labor_entered_hours', '1.00') : '1.00'), laborAdjustment: @js($oldBelongs ? old('labor_adjustment', 'normal') : 'normal'), laborCustomFactor: @js($oldBelongs ? old('labor_adjustment_factor', '1.25') : '1.25'), laborReason: @js($oldBelongs ? old('labor_adjustment_reason', '') : ''), laborReasonCustom: @js($oldBelongs ? old('labor_adjustment_reason_custom', '') : ''), laborHoursOverridden: {{ $oldBelongs && old('labor_hours_overridden') ? 'true' : 'false' }}, laborFinalHours: @js($oldBelongs ? old('quantity', '1.00') : '1.00'), laborOverrideReason: @js($oldBelongs ? old('labor_override_reason', '') : ''), laborRateOverrideReason: @js($oldBelongs ? old('labor_rate_override_reason', '') : ''), laborAdjustExpanded: @js($laborStoreNeedsAdvanced) }, partsMatrices, @js($concernPartsMatrixKey), '{{ route('operations.repair-orders.lines.pricing-preview', $repairOrder) }}')"
                    >
                        @csrf
                        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                        <input type="hidden" name="repair_order_concern_id" value="{{ $concern->id }}">
                        <input type="hidden" name="repair_order_work_group_id" value="{{ $workGroup->id }}">
                        <input type="hidden" name="type" x-model="type">
                        @if ($oldBelongs && $errors->any())
                            <p class="mb-2 text-xs font-semibold text-rose-700" data-workspace-modal-validation role="alert">
                                {{ $errors->first() ?: 'Could not save this line. Check the fields and try again.' }}
                            </p>
                        @endif

                        <template x-if="hasLineType() && type !== 'part'">
                            <div class="mb-2" data-line-entry-panel="simple">
                                @include('operations.repair-orders.partials.repair-order-simple-line-entry', [
                                    'description' => $defaultLineDescription,
                                    'quantity' => $oldBelongs ? old('quantity', '1.00') : '1.00',
                                    'defaultNotesPrivate' => $defaultNotesPrivate,
                                    'laborCategories' => $laborCategories,
                                    'suppressLaborDescription' => $suppressComposeLaborDescription,
                                    'repairTitle' => $workGroup->title,
                                    'laborMemoryUrl' => route('operations.repair-orders.labor-memory-suggest', $repairOrder),
                                    'repairOrder' => $repairOrder,
                                ])
                            </div>
                        </template>
                        <template x-if="hasLineType() && type === 'part'">
                            <div class="mb-2" data-line-entry-panel="part">
                                @include('operations.repair-orders.partials.repair-order-part-line-entry', [
                                    'partsMatrices' => $partsMatrices,
                                    'description' => $defaultLineDescription,
                                    'quantity' => $oldBelongs ? old('quantity', '1.00') : '1.00',
                                    'vendorName' => $oldBelongs ? old('vendor_name') : '',
                                    'partNumber' => $oldBelongs ? old('part_number') : '',
                                    'sourcingNotes' => $oldBelongs ? old('sourcing_notes') : '',
                                    'partSource' => $oldBelongs ? old('part_source') : null,
                                    'partClassification' => $oldBelongs ? old('part_classification') : null,
                                    'partWarrantyImpact' => $oldBelongs ? old('part_warranty_impact') : null,
                                    'hasCore' => $oldBelongs && old('has_core'),
                                    'saveOldPart' => $oldBelongs && old('save_old_part'),
                                ])
                            </div>
                        </template>
                    </form>
                </div>
            @endforeach

            {{-- Scope-level line create (no Repair Action) — diagnostic concerns, standalone notes --}}
            @php
                $scopeOldBelongs = filled(old('repair_order_concern_id'))
                    && (string) old('repair_order_concern_id') === (string) $concern->id
                    && blank(old('repair_order_work_group_id'));
                $scopeDefaultLineType = $scopeOldBelongs ? old('type', '') : '';
                $scopeDefaultLineDescription = $scopeOldBelongs ? old('description', '') : '';
                $scopeDefaultLineSell = $scopeOldBelongs
                    ? old('unit_price', in_array(old('type'), ['part'], true) ? $concernDefaultPartSell : (in_array(old('type'), ['note'], true) ? '0' : $concernDefaultLaborRate))
                    : '';
                $scopeLaborStoreNeedsAdvanced = $scopeOldBelongs && (
                    old('labor_adjustment', 'normal') !== 'normal'
                    || old('labor_category_key', $concernDefaultLaborCategoryKey) !== $concernDefaultLaborCategoryKey
                    || old('labor_hours_overridden')
                    || filled(old('labor_override_reason'))
                );
            @endphp
            <div
                class="ops-workspace-modal__panel"
                x-show="['labor','part','sublet','note'].includes(task) && String(context.concernId) === '{{ $concern->id }}' && (context.workGroupId == null || context.workGroupId === '')"
                x-cloak
            >
                <form
                    id="workspace-line-create-concern-{{ $concern->id }}"
                    method="POST"
                    action="{{ route('operations.repair-orders.lines.store', $repairOrder) }}"
                    data-workspace-modal-form="line-create"
                    data-refresh-scope="worksheet"
                    data-saving-label="Saving…"
                    @submit.prevent="submitLine($event)"
                    x-data="arkPartPricing({ type: '{{ $scopeDefaultLineType }}', concernSummary: @js($concern->summary), pricingMode: '{{ $scopeOldBelongs ? old('pricing_mode', $concernDefaultPartPricingMode) : $concernDefaultPartPricingMode }}', defaultPricingMode: '{{ $concernDefaultPartPricingMode }}', defaultPartSell: '{{ $concernDefaultPartSell }}', matrixKey: '{{ $scopeOldBelongs ? old('pricing_matrix_key') : '' }}', explicitMatrix: {{ $scopeOldBelongs && old('pricing_matrix_explicit') ? 'true' : 'false' }}, cost: '{{ $scopeOldBelongs ? old('part_cost') : '' }}', sell: '{{ $scopeDefaultLineSell }}', sellEdited: {{ $scopeOldBelongs && (old('unit_price_override') || old('labor_rate_overridden')) ? 'true' : 'false' }}, defaultLaborRate: '{{ $concernDefaultLaborRate }}', laborCategories: @js($laborCategories), defaultLaborCategoryKey: @js($concernDefaultLaborCategoryKey), laborCategoryKey: @js($scopeOldBelongs ? old('labor_category_key', $concernDefaultLaborCategoryKey) : $concernDefaultLaborCategoryKey), laborEnteredHours: @js($scopeOldBelongs ? old('labor_entered_hours', '1.00') : '1.00'), laborAdjustment: @js($scopeOldBelongs ? old('labor_adjustment', 'normal') : 'normal'), laborCustomFactor: @js($scopeOldBelongs ? old('labor_adjustment_factor', '1.25') : '1.25'), laborReason: @js($scopeOldBelongs ? old('labor_adjustment_reason', '') : ''), laborReasonCustom: @js($scopeOldBelongs ? old('labor_adjustment_reason_custom', '') : ''), laborHoursOverridden: {{ $scopeOldBelongs && old('labor_hours_overridden') ? 'true' : 'false' }}, laborFinalHours: @js($scopeOldBelongs ? old('quantity', '1.00') : '1.00'), laborOverrideReason: @js($scopeOldBelongs ? old('labor_override_reason', '') : ''), laborRateOverrideReason: @js($scopeOldBelongs ? old('labor_rate_override_reason', '') : ''), laborAdjustExpanded: @js($scopeLaborStoreNeedsAdvanced) }, partsMatrices, @js($concernPartsMatrixKey), '{{ route('operations.repair-orders.lines.pricing-preview', $repairOrder) }}')"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <input type="hidden" name="repair_order_concern_id" value="{{ $concern->id }}">
                    <input type="hidden" name="type" x-model="type">
                    @if ($scopeOldBelongs && $errors->any())
                        <p class="mb-2 text-xs font-semibold text-rose-700" data-workspace-modal-validation role="alert">
                            {{ $errors->first() ?: 'Could not save this line. Check the fields and try again.' }}
                        </p>
                    @endif

                    <template x-if="hasLineType() && type !== 'part'">
                        <div class="mb-2" data-line-entry-panel="simple">
                            @include('operations.repair-orders.partials.repair-order-simple-line-entry', [
                                'description' => $scopeDefaultLineDescription,
                                'quantity' => $scopeOldBelongs ? old('quantity', '1.00') : '1.00',
                                'defaultNotesPrivate' => $defaultNotesPrivate,
                                'laborCategories' => $laborCategories,
                                'suppressLaborDescription' => false,
                                'repairTitle' => $concern->summary,
                                'laborMemoryUrl' => route('operations.repair-orders.labor-memory-suggest', $repairOrder),
                                'repairOrder' => $repairOrder,
                            ])
                        </div>
                    </template>
                    <template x-if="hasLineType() && type === 'part'">
                        <div class="mb-2" data-line-entry-panel="part">
                            @include('operations.repair-orders.partials.repair-order-part-line-entry', [
                                'partsMatrices' => $partsMatrices,
                                'description' => $scopeDefaultLineDescription,
                                'quantity' => $scopeOldBelongs ? old('quantity', '1.00') : '1.00',
                                'vendorName' => $scopeOldBelongs ? old('vendor_name') : '',
                                'partNumber' => $scopeOldBelongs ? old('part_number') : '',
                                'sourcingNotes' => $scopeOldBelongs ? old('sourcing_notes') : '',
                                'partSource' => $scopeOldBelongs ? old('part_source') : null,
                                'partClassification' => $scopeOldBelongs ? old('part_classification') : null,
                                'partWarrantyImpact' => $scopeOldBelongs ? old('part_warranty_impact') : null,
                                'hasCore' => $scopeOldBelongs && old('has_core'),
                                'saveOldPart' => $scopeOldBelongs && old('save_old_part'),
                            ])
                        </div>
                    </template>
                </form>
            </div>

            {{-- Add Repair Action for this concern --}}
            <div
                class="ops-workspace-modal__panel"
                x-show="task === 'repair-action' && String(context.concernId) === '{{ $concern->id }}'"
                x-cloak
            >
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.concerns.work-groups.store', [$repairOrder, $concern]) }}"
                    data-workspace-modal-form="repair-action"
                    data-refresh-scope="worksheet"
                    data-saving-label="Saving…"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <input type="hidden" name="repair_order_concern_id" value="{{ $concern->id }}">
                    <label class="block text-[11px] font-medium text-slate-500">
                        What are we doing?
                        <input
                            name="title"
                            required
                            value="{{ (string) old('repair_order_concern_id') === (string) $concern->id ? old('title') : '' }}"
                            placeholder="e.g. Replace water pump"
                            class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-950 placeholder:text-slate-400"
                        >
                    </label>
                    <p class="mt-1 text-[11px] text-slate-400">Name the repair — labor hours hang under it.</p>
                </form>
            </div>
        @endforeach

        {{-- Engine Oil --}}
        <div class="ops-workspace-modal__panel" x-show="task === 'oil'" x-cloak>
            <form
                method="POST"
                action="{{ route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder) }}"
                data-workspace-modal-form="oil"
                data-refresh-scope="worksheet"
                data-saving-label="Saving…"
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <p class="text-sm text-slate-700">
                    Authorize Engine Oil Service on this repair order. ARK creates the concern, repair action, and package line through Maintenance Service authority.
                </p>
                @if ($engineOilServices->isNotEmpty())
                    <p class="mt-3 text-xs text-amber-800">
                        An Engine Oil Service already exists on this repair order. Submitting again is idempotent.
                    </p>
                @endif
            </form>
        </div>

        {{-- Testing Package --}}
        <div class="ops-workspace-modal__panel" x-show="task === 'testing'" x-cloak>
            <form
                method="POST"
                action="{{ route('operations.repair-orders.work-authorization.testing.store', $repairOrder) }}"
                data-workspace-modal-form="testing"
                data-refresh-scope="worksheet"
                data-saving-label="Saving…"
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <p class="text-sm text-slate-700">
                    Authorize a Testing Package. ARK creates the concern, repair action, and package line through Work Authorization.
                </p>
            </form>
        </div>

        {{-- Saved Work (Rapid Work Templates + Historical Work Recall) --}}
        <div
            class="ops-workspace-modal__panel"
            x-show="task === 'saved-work'"
            x-cloak
            x-data="arkSavedWorkPicker({
                searchUrl: @js(route('operations.work-templates.search')),
                recallUrlTemplate: @js('/app/repair-orders/'.$repairOrder->id.'/work-templates/__TEMPLATE__/historical-recall'),
                assistUrlTemplate: @js('/app/repair-orders/'.$repairOrder->id.'/work-templates/__TEMPLATE__/historical-recall/assist'),
                assistStatusUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-assist/__ASSIST__'),
                concernId: context.concernId || null,
                intents: @js(collect(\App\Ark\Operations\RepairOrders\RecommendationIntent::cases())->map(fn ($intent) => ['value' => $intent->value, 'label' => $intent->staffLabel()])->values()->all()),
                defaultIntent: @js(\App\Ark\Operations\RepairOrders\RecommendationIntent::Maintenance->value),
            })"
            x-init="boot()"
        >
            <form
                method="POST"
                action="{{ route('operations.repair-orders.work-templates.apply', $repairOrder) }}"
                data-workspace-modal-form="saved-work"
                data-refresh-scope="worksheet"
                data-saving-label="Adding…"
                @submit.prevent="if (! canSubmit()) { return; }; $root.submitWorksheetForm($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="work_template_id" :value="selectedId || ''">
                <input type="hidden" name="repair_order_concern_id" :value="concernId || ''">
                <input type="hidden" name="recommendation_intent" :value="concernId ? '' : recommendationIntent">
                <input type="hidden" name="historical_labor_hours" :value="applyHoursValue()">
                <input type="hidden" name="historical_match_tier" :value="recall?.tier || ''">
                <input type="hidden" name="historical_labor_confirmed" :value="laborConfirmed ? '1' : '0'">

                <label class="block text-xs font-medium text-slate-500">
                    Search common jobs
                    <input
                        type="search"
                        x-model="query"
                        @input.debounce.200ms="search()"
                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                        placeholder="front bra…"
                        autocomplete="off"
                    >
                </label>

                <ul class="mt-3 max-h-40 space-y-1 overflow-y-auto" role="listbox">
                    <template x-for="item in results" :key="item.id">
                        <li>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded border px-2.5 py-2 text-left text-sm"
                                :class="selectedId === item.id ? 'border-slate-900 bg-slate-950 text-white' : 'border-slate-200 bg-white text-slate-800 hover:bg-slate-50'"
                                @click="select(item)"
                            >
                                <span class="font-semibold" x-text="item.title"></span>
                            </button>
                        </li>
                    </template>
                    <li x-show="! loading && results.length === 0" class="px-1 py-2 text-xs text-slate-500">No matching Common Jobs.</li>
                </ul>

                <div x-show="selected" x-cloak class="mt-3 space-y-3 rounded border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
                    <p class="text-sm font-bold text-slate-950" x-text="selected?.title"></p>
                    <p class="text-slate-500" x-show="concernId">Adds a Repair Action under this concern.</p>

                    <label x-show="! concernId" x-cloak class="block text-[11px] font-medium text-slate-500">
                        Recommendation status
                        <select x-model="recommendationIntent" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-950">
                            <template x-for="opt in intents" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                        <span class="mt-1 block font-normal text-slate-400">Creates a new concern with this status. Change it if this job is not Maintenance.</span>
                    </label>

                    <div x-show="recallLoading" class="text-slate-500">Checking shop history…</div>

                    <div x-show="! recallLoading && recall && recall.tier !== 'none'" class="rounded border border-slate-200 bg-white px-2.5 py-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Historical Recall</span>
                            <span
                                class="rounded border border-slate-300 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700"
                                x-text="recall?.tier_label"
                            ></span>
                        </div>
                        <p class="mt-1 font-medium text-slate-800" x-text="recall?.comparable_vehicle_summary"></p>
                        <p class="mt-0.5 text-slate-600" x-text="recall?.sample_label"></p>
                        <template x-for="reason in (recall?.reasons || [])" :key="reason">
                            <p class="mt-0.5 text-slate-500" x-text="reason"></p>
                        </template>
                    </div>

                    <div x-show="assistStatus === 'reviewing'" class="text-slate-500">Dragon reviewing…</div>

                    <div
                        x-show="assist?.available"
                        x-cloak
                        class="rounded border border-slate-200 bg-white px-2.5 py-2"
                    >
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Dragon Assist</p>
                        <p class="mt-1 text-slate-800" x-text="assist?.summary"></p>
                        <p x-show="assist?.confidence_comment" class="mt-1 text-slate-600" x-text="assist?.confidence_comment"></p>
                        <template x-for="caution in (assist?.cautions || [])" :key="caution">
                            <p class="mt-1 text-amber-800">Caution: <span x-text="caution"></span></p>
                        </template>
                        <p x-show="assist?.recommendation" class="mt-1 text-slate-600" x-text="assist?.recommendation"></p>
                    </div>

                    <div x-show="! recallLoading && recall && recall.tier === 'none'" class="text-slate-500">
                        No comparable shop history found.
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Labor</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <label class="inline-flex items-center gap-1.5 text-sm text-slate-900">
                                <span x-show="recall?.prepares_labor">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        x-model="laborHours"
                                        class="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                                    >
                                    <span>hr</span>
                                </span>
                                <span x-show="! recall?.prepares_labor">
                                    <template x-for="line in (selected?.lines || []).filter(l => l.type === 'labor')" :key="'labor-'+line.description">
                                        <span>
                                            <span x-text="line.description"></span>
                                            <span x-show="line.hours" x-text="' — ' + line.hours + ' hr'"></span>
                                        </span>
                                    </template>
                                </span>
                            </label>
                        </div>
                        <p class="mt-1 text-slate-500" x-text="recall?.source_label"></p>
                        <p
                            x-show="recall?.prepares_labor && recall?.min_hours != null"
                            class="text-slate-500"
                            x-text="recall ? ('Range ' + recall.min_hours + '–' + recall.max_hours + ' hr') : ''"
                        ></p>
                        <p
                            x-show="recall?.tier === 'possible' && recall?.median_hours != null"
                            class="mt-1 text-slate-500"
                            x-text="recall ? ('Historical reference: ' + recall.median_hours + ' hr') : ''"
                        ></p>
                        <label
                            x-show="recall?.requires_review"
                            class="mt-2 flex items-start gap-2 text-slate-700"
                        >
                            <input type="checkbox" class="mt-0.5 rounded border-slate-300" x-model="laborConfirmed">
                            <span>Use suggested hours from shop history</span>
                        </label>
                    </div>

                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Parts</p>
                        <template x-for="line in (selected?.lines || []).filter(l => l.type === 'part')" :key="'part-'+line.description">
                            <p class="mt-0.5">
                                <span x-text="line.description"></span>
                                <span x-show="Number(line.quantity) > 1" x-text="' ×' + line.quantity"></span>
                            </p>
                        </template>
                    </div>
                </div>
            </form>
        </div>

        @include('operations.repair-orders.partials.workspace-modal.present-panels', [
            'repairOrder' => $repairOrder,
            'estimateVersion' => $estimateVersion,
            'technicians' => $technicians ?? collect(),
        ])

        {{-- Edit existing line --}}
        @if ($editingLineId)
            @php
                $editingLine = $repairOrder->lines->firstWhere('id', $editingLineId);
            @endphp
            @if ($editingLine)
                <div class="ops-workspace-modal__panel" x-show="task === 'edit-line'" x-cloak data-workspace-modal-form="edit-line-wrap">
                    @php
                        $editConcern = $repairOrder->concerns->firstWhere('id', $editingLine->repair_order_concern_id);
                        $editWorkGroup = $editConcern?->workGroups->firstWhere('id', $editingLine->repair_order_work_group_id);
                    @endphp
                    @if ($editConcern)
                        @include('operations.repair-orders.partials.workspace-modal.edit-line', [
                            'line' => $editingLine,
                            'concern' => $editConcern,
                            'workGroup' => $editWorkGroup,
                            'repairOrder' => $repairOrder,
                            'estimateVersion' => $estimateVersion,
                            'totals' => $totals,
                            'partsMatrices' => $partsMatrices,
                            'laborCategories' => $laborCategories,
                            'defaultLaborRate' => $defaultLaborRate,
                            'defaultNotesPrivate' => $defaultNotesPrivate,
                            'partstechShopSettings' => $partstechShopSettings ?? \App\Ark\Operations\Settings\ShopSettings::current(),
                            'isTerminal' => $isTerminal,
                        ])
                    @endif
                </div>
            @endif
        @endif
        @endunless

        {{-- Evidence: list when present; attach only when writable --}}
        <div class="ops-workspace-modal__panel" x-show="task === 'evidence'" x-cloak>
            @if ($evidenceItems->isNotEmpty())
                <ul class="ops-workspace-modal__evidence-list mb-4">
                    @foreach ($evidenceItems as $item)
                        <li class="ops-workspace-modal__evidence-item">
                            <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="ops-workspace-modal__evidence-thumb">
                                @if ($item['is_image'])
                                    <img src="{{ $item['url'] }}" alt="" class="h-full w-full object-cover">
                                @elseif ($item['is_video'])
                                    <span>VIDEO</span>
                                @else
                                    <span>PDF</span>
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-900">
                                    {{ $item['type_label'] }}
                                    @if ($item['is_primary'])
                                        <span class="ml-1 text-[10px] font-semibold uppercase text-amber-800">Primary</span>
                                    @endif
                                </p>
                                @if (filled($item['caption']))
                                    <p class="text-xs text-slate-600">{{ $item['caption'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            @unless ($isTerminal)
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.evidence.store', $repairOrder) }}"
                    enctype="multipart/form-data"
                    data-workspace-modal-form="evidence"
                    data-refresh-scope="worksheet"
                    data-saving-label="Saving…"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    <div class="grid gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600" for="workspace-evidence-file">File</label>
                            <input id="workspace-evidence-file" name="file" type="file" required accept="image/*,video/*,application/pdf" class="mt-0.5 w-full text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600" for="workspace-evidence-target">Attach to</label>
                            <select
                                id="workspace-evidence-target"
                                name="attachable_id"
                                required
                                class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                                onchange="document.getElementById('workspace-evidence-attachable-kind').value = this.options[this.selectedIndex].dataset.kind"
                            >
                                <option value="{{ $repairOrder->id }}" data-kind="repair_order" selected>General (this RO)</option>
                                @foreach ($evidenceConcerns as $concern)
                                    <option value="{{ $concern['id'] }}" data-kind="concern">{{ \Illuminate\Support\Str::limit($concern['summary'], 40) }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="attachable_kind" id="workspace-evidence-attachable-kind" value="repair_order">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600" for="workspace-evidence-caption">Caption</label>
                            <input id="workspace-evidence-caption" name="caption" type="text" maxlength="500" placeholder="What was seen (not a diagnosis)" class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                        </div>
                        <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-700">
                            <input type="checkbox" name="as_primary" value="1" class="rounded border-slate-300">
                            Primary
                        </label>
                        <input type="hidden" name="source" value="upload">
                    </div>
                </form>
            @endunless
        </div>

        {{-- Customer / RO Documents — paperwork, not Evidence --}}
        <div class="ops-workspace-modal__panel" x-show="task === 'document'" x-cloak>
            @php
                $roDocuments = $customerDocuments ?? collect();
            @endphp

            @unless ($isTerminal)
                @include('operations.documents.partials.add-document-panel', [
                    'customer' => $repairOrder->customer,
                    'repairOrder' => $repairOrder,
                    'storeUrl' => route('operations.repair-orders.documents.store', $repairOrder),
                    'scanUrl' => route('operations.repair-orders.documents.scan', $repairOrder),
                    'attachUrl' => route('operations.repair-orders.documents.attach', $repairOrder),
                    'attachableDocuments' => $attachableDocuments ?? collect(),
                ])
            @endunless

            @if ($roDocuments->isNotEmpty())
                <ul @class([
                    'divide-y divide-slate-100 rounded-sm border border-slate-200',
                    'mt-4' => ! $isTerminal,
                ])>
                    @foreach ($roDocuments as $doc)
                        <li
                            class="px-3 py-2"
                            x-data="{ emailOpen: false }"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <a
                                        href="{{ $doc['viewer_url'] }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="block truncate text-sm font-semibold text-slate-950 underline-offset-2 hover:underline"
                                    >{{ $doc['title'] }}</a>
                                    <p class="text-[11px] text-slate-600">
                                        {{ $doc['type_label'] }}
                                        @if (($doc['email_send_count'] ?? 0) > 0)
                                            <span class="mx-1 text-slate-300">·</span>
                                            Emailed {{ $doc['email_send_count'] }}×
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <a
                                        href="{{ $doc['viewer_url'] }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-[11px] font-semibold text-slate-700 underline-offset-2 hover:underline"
                                    >Open</a>
                                    @canany([
                                        \App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value,
                                        \App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value,
                                    ])
                                        <button
                                            type="button"
                                            class="text-[11px] font-semibold text-slate-900 underline-offset-2 hover:underline"
                                            @click="emailOpen = ! emailOpen"
                                        >Email</button>
                                    @endcanany
                                </div>
                            </div>
                            <div class="mt-2" x-show="emailOpen" x-cloak>
                                @include('operations.documents.partials.document-email-form', [
                                    'customer' => $repairOrder->customer,
                                    'doc' => $doc,
                                    'compact' => true,
                                ])
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @include('operations.repair-orders.partials.workspace-modal.identity-panels', [
            'repairOrder' => $repairOrder,
            'estimateVersion' => $estimateVersion,
            'isTerminal' => $isTerminal,
        ])

        {{-- Review request: closeout (closePaid) and post-close follow-up --}}
        @php
            $reviewRequestModal = $reviewRequest ?? app(\App\Ark\Operations\Messaging\ReviewRequestProjection::class)->for($repairOrder);
        @endphp
        <div class="ops-workspace-modal__panel" x-show="task === 'review-request' && context.closePaid" x-cloak>
            @include('operations.repair-orders.partials.repair-order-review-request-panel', [
                'repairOrder' => $repairOrder,
                'estimateVersion' => $estimateVersion,
                'reviewRequest' => $reviewRequestModal,
                'closePaid' => true,
                'modal' => true,
            ])
        </div>
        <div class="ops-workspace-modal__panel" x-show="task === 'review-request' && ! context.closePaid" x-cloak>
            @include('operations.repair-orders.partials.repair-order-review-request-panel', [
                'repairOrder' => $repairOrder,
                'estimateVersion' => $estimateVersion,
                'reviewRequest' => $reviewRequestModal,
                'closePaid' => false,
                'modal' => true,
            ])
        </div>
    </x-operations.workspace-modal>

