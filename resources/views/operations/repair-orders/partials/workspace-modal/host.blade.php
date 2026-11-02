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
    <x-tabler.workspace-modal
        :initial-task="$isTerminal ? null : $initialTask"
        :initial-context="$isTerminal ? [] : $initialContext"
    >
        @unless ($isTerminal)
        {{-- Add Work chooser - radio list, not a menu of buttons --}}
        <div data-workspace-modal-panel x-show="task === 'add-work'" x-cloak>
            <fieldset class="form-selectgroup form-selectgroup-boxes d-flex flex-column" data-workspace-add-work-chooser>
                <legend class="visually-hidden">What would you like to add?</legend>
                @foreach ([
                    'concern' => ['Customer Concern', 'The problem or request the customer brought in.'],
                    'oil' => ['Engine Oil Service', 'Authorize a maintenance oil package for this vehicle.'],
                    'testing' => ['Testing Package', 'Authorize diagnostic or inspection testing.'],
                    'saved-work' => ['Common Job', 'Add a common job from a shop template - labor, parts, and fees in one step.'],
                ] as $choiceValue => [$choiceTitle, $choiceHint])
                    <label class="form-selectgroup-item flex-fill">
                        <input
                            type="radio"
                            class="form-selectgroup-input"
                            name="add_work_choice"
                            value="{{ $choiceValue }}"
                            x-model="addWorkChoice"
                        >
                        <span class="form-selectgroup-label d-flex align-items-center p-3 text-start">
                            <span class="me-3">
                                <span class="form-selectgroup-check"></span>
                            </span>
                            <span>
                                <span class="d-block fw-bold text-body">{{ $choiceTitle }}</span>
                                <span class="d-block small text-secondary">{{ $choiceHint }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </fieldset>
        </div>
        @endunless

        @unless ($isTerminal)
        {{-- Concern --}}
        <div data-workspace-modal-panel x-show="task === 'concern'" x-cloak>
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
                    id="concern-authoring-line-{{ $workGroup->id }}"
                    data-concern-authoring="{{ $concern->id }}"
                    data-workspace-modal-panel
                    x-show="['labor','part','sublet','note'].includes(task) && String(context.workGroupId) === '{{ $workGroup->id }}'"
                    x-cloak
                >
                    <form
                        id="workspace-line-create-{{ $workGroup->id }}"
                        method="POST"
                        action="{{ route('operations.repair-orders.lines.store', $repairOrder) }}"
                        data-workspace-modal-form="line-create"
                        data-worksheet-continuity
                        data-continuity-region="composer"
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
                            <div class="alert alert-danger mb-2" data-workspace-modal-validation role="alert">
                                {{ $errors->first() ?: 'Could not save this line. Check the fields and try again.' }}
                            </div>
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
                                    'showSubmit' => false,
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
                                    'showSubmit' => false,
                                ])
                            </div>
                        </template>
                    </form>
                </div>
            @endforeach

            {{-- Scope-level line create (no Repair Action) - diagnostic concerns, standalone notes --}}
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
                id="concern-authoring-line-concern-{{ $concern->id }}"
                data-concern-authoring="{{ $concern->id }}"
                data-workspace-modal-panel
                x-show="['labor','part','sublet','note'].includes(task) && String(context.concernId) === '{{ $concern->id }}' && (context.workGroupId == null || context.workGroupId === '')"
                x-cloak
            >
                <form
                    id="workspace-line-create-concern-{{ $concern->id }}"
                    method="POST"
                    action="{{ route('operations.repair-orders.lines.store', $repairOrder) }}"
                    data-workspace-modal-form="line-create"
                    data-worksheet-continuity
                    data-continuity-region="composer"
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
                        <div class="alert alert-danger mb-2" data-workspace-modal-validation role="alert">
                            {{ $errors->first() ?: 'Could not save this line. Check the fields and try again.' }}
                        </div>
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
                                'showSubmit' => false,
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
                                'showSubmit' => false,
                            ])
                        </div>
                    </template>
                </form>
            </div>

            {{-- Add Repair Action for this concern --}}
            <div
                data-workspace-modal-panel
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
                    <label class="form-label" for="workspace-repair-action-title-{{ $concern->id }}">What are we doing?</label>
                    <input
                        id="workspace-repair-action-title-{{ $concern->id }}"
                        name="title"
                        required
                        value="{{ (string) old('repair_order_concern_id') === (string) $concern->id ? old('title') : '' }}"
                        placeholder="e.g. Replace water pump"
                        class="form-control"
                    >
                    <div class="form-hint">Name the repair - labor hours hang under it.</div>
                </form>
            </div>
        @endforeach

        {{-- Engine Oil --}}
        <div data-workspace-modal-panel x-show="task === 'oil'" x-cloak>
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
                <p class="mb-0">
                    Authorize Engine Oil Service on this repair order. ARK creates the concern, repair action, and package line through Maintenance Service authority.
                </p>
                @if ($engineOilServices->isNotEmpty())
                    <div class="alert alert-warning mt-3 mb-0">
                        An Engine Oil Service already exists on this repair order. Submitting again is idempotent.
                    </div>
                @endif
            </form>
        </div>

        {{-- Testing Package --}}
        <div data-workspace-modal-panel x-show="task === 'testing'" x-cloak>
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
                <p class="mb-0">
                    Authorize a Testing Package. ARK creates the concern, repair action, and package line through Work Authorization.
                </p>
            </form>
        </div>

        {{-- Saved Work (Rapid Work Templates + Historical Work Recall) --}}
        <div
            data-workspace-modal-panel
            x-show="task === 'saved-work'"
            x-cloak
            x-data="arkSavedWorkPicker({
                searchUrl: @js(route('operations.work-templates.search')),
                recallUrlTemplate: @js('/app/repair-orders/'.$repairOrder->id.'/work-templates/__TEMPLATE__/historical-recall'),
                assistUrlTemplate: @js('/app/repair-orders/'.$repairOrder->id.'/work-templates/__TEMPLATE__/historical-recall/assist'),
                assistStatusUrlTemplate: @js('/app/repair-orders/'.$repairOrder->getRouteKey().'/dragon-assist/__ASSIST__'),
                concernId: context.concernId || null,
                priorities: @js(collect(\App\Ark\Operations\RepairOrders\RecommendationPriority::cases())->map(fn ($priority) => ['value' => $priority->value, 'label' => $priority->staffLabel()])->push(['value' => 'information_only', 'label' => 'Information Only'])->values()->all()),
                defaultPosture: @js(''),
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
                <input type="hidden" name="recommendation_posture" :value="concernId ? '' : recommendationPosture">
                <input type="hidden" name="historical_labor_hours" :value="applyHoursValue()">
                <input type="hidden" name="historical_match_tier" :value="recall?.tier || ''">
                <input type="hidden" name="historical_labor_confirmed" :value="laborConfirmed ? '1' : '0'">

                <label class="form-label" for="workspace-saved-work-search">Search common jobs</label>
                <input
                    id="workspace-saved-work-search"
                    type="search"
                    x-model="query"
                    @input.debounce.200ms="search()"
                    class="form-control"
                    placeholder="front bra…"
                    autocomplete="off"
                >

                <div class="list-group mt-3 overflow-auto" style="max-height: 10rem" role="listbox" aria-label="Common jobs">
                    <template x-for="item in results" :key="item.id">
                        <button
                            type="button"
                            class="list-group-item list-group-item-action fw-bold"
                            role="option"
                            :class="selectedId === item.id ? 'active' : ''"
                            :aria-selected="selectedId === item.id"
                            @click="select(item)"
                            x-text="item.title"
                        ></button>
                    </template>
                    <div x-show="! loading && results.length === 0" class="list-group-item small text-secondary">No matching Common Jobs.</div>
                </div>

                <div x-show="selected" x-cloak class="card card-sm mt-3" data-saved-work-selected>
                    <div class="card-body small">
                        <div class="fw-bold mb-1" x-text="selected?.title"></div>
                        <p class="text-secondary mb-3" x-show="concernId">Adds a Repair Action under this concern.</p>

                        <div x-show="! concernId" x-cloak class="mb-3">
                            <label class="form-label" for="workspace-saved-work-priority">Priority</label>
                            <select id="workspace-saved-work-priority" x-model="recommendationPosture" class="form-select">
                                <option value="">Use saved work priority</option>
                                <template x-for="opt in priorities" :key="opt.value">
                                    <option :value="opt.value" x-text="opt.label"></option>
                                </template>
                            </select>
                        </div>

                        <div x-show="recallLoading" class="text-secondary mb-3">Checking shop history…</div>

                        <div x-show="! recallLoading && recall && recall.tier !== 'none'" class="border rounded p-2 mb-3">
                            <div class="subheader mb-1">
                                Historical Recall
                                <span class="badge bg-secondary-lt ms-1" x-text="recall?.tier_label"></span>
                            </div>
                            <div class="fw-medium" x-text="recall?.comparable_vehicle_summary"></div>
                            <div class="text-secondary" x-text="recall?.sample_label"></div>
                            <template x-for="reason in (recall?.reasons || [])" :key="reason">
                                <div class="text-secondary" x-text="reason"></div>
                            </template>
                        </div>

                        <div x-show="assistStatus === 'reviewing'" class="text-secondary mb-3">Dragon reviewing…</div>

                        <div x-show="assist?.available" x-cloak class="border rounded p-2 mb-3">
                            <div class="subheader mb-1">Dragon Assist</div>
                            <div x-text="assist?.summary"></div>
                            <div x-show="assist?.confidence_comment" class="text-secondary mt-1" x-text="assist?.confidence_comment"></div>
                            <template x-for="caution in (assist?.cautions || [])" :key="caution">
                                <div class="text-orange mt-1">Caution: <span x-text="caution"></span></div>
                            </template>
                            <div x-show="assist?.recommendation" class="text-secondary mt-1" x-text="assist?.recommendation"></div>
                        </div>

                        <div x-show="! recallLoading && recall && recall.tier === 'none'" class="text-secondary mb-3">
                            No comparable shop history found.
                        </div>

                        <div class="mb-3">
                            <div class="subheader mb-1">Labor</div>
                            <span x-show="recall?.prepares_labor">
                                <label class="visually-hidden" for="workspace-saved-work-hours">Labor hours</label>
                                <input
                                    id="workspace-saved-work-hours"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    x-model="laborHours"
                                    class="form-control form-control-sm d-inline-block"
                                    style="width: 5rem"
                                >
                                <span>hr</span>
                            </span>
                            <span x-show="! recall?.prepares_labor">
                                <template x-for="line in (selected?.lines || []).filter(l => l.type === 'labor')" :key="'labor-'+line.description">
                                    <span>
                                        <span x-text="line.description"></span>
                                        <span x-show="line.hours" x-text="' - ' + line.hours + ' hr'"></span>
                                    </span>
                                </template>
                            </span>
                            <div class="text-secondary mt-1" x-text="recall?.source_label"></div>
                            <div
                                x-show="recall?.prepares_labor && recall?.min_hours != null"
                                class="text-secondary"
                                x-text="recall ? ('Range ' + recall.min_hours + '–' + recall.max_hours + ' hr') : ''"
                            ></div>
                            <div
                                x-show="recall?.tier === 'possible' && recall?.median_hours != null"
                                class="text-secondary mt-1"
                                x-text="recall ? ('Historical reference: ' + recall.median_hours + ' hr') : ''"
                            ></div>
                            <label x-show="recall?.requires_review" class="form-check mt-2 mb-0">
                                <input type="checkbox" class="form-check-input" x-model="laborConfirmed">
                                <span class="form-check-label">Use suggested hours from shop history</span>
                            </label>
                        </div>

                        <div>
                            <div class="subheader mb-1">Parts</div>
                            <template x-for="line in (selected?.lines || []).filter(l => l.type === 'part')" :key="'part-'+line.description">
                                <div>
                                    <span x-text="line.description"></span>
                                    <span x-show="Number(line.quantity) > 1" x-text="' ×' + line.quantity"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @include('operations.repair-orders.partials.workspace-modal.present-panels', [
            'repairOrder' => $repairOrder,
            'estimateVersion' => $estimateVersion,
            'technicians' => $technicians ?? collect(),
        ])

        @unless ($isTerminal)
            @include('operations.repair-orders.partials.repair-order-deferred-add-forms')
        @endunless

        {{-- Edit existing line. Opening this mount does not refresh the worksheet. --}}
        @php
            $editingLine = $editingLineId ? $repairOrder->lines->firstWhere('id', $editingLineId) : null;
        @endphp
        <div
            id="worksheet-line-editor"
            @if ($editingLine)
                data-editor-line-id="{{ $editingLine->id }}"
                data-editor-line-label="{{ $editingLine->type?->staffLabel() ?? 'Line' }}"
                data-editor-line-type="{{ $editingLine->type?->value }}"
            @endif
        >
            @if ($editingLine)
                <div data-workspace-modal-panel x-show="task === 'edit-line'" x-cloak data-workspace-modal-form="edit-line-wrap">
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
        </div>
        @endunless

        {{-- Evidence: list when present; attach only when writable --}}
        <div data-workspace-modal-panel x-show="task === 'evidence'" x-cloak>
            @if ($evidenceItems->isNotEmpty())
                <div class="list-group mb-3" data-workspace-evidence-list>
                    @foreach ($evidenceItems as $item)
                        <div class="list-group-item d-flex align-items-center gap-3">
                            <a
                                href="{{ $item['url'] }}"
                                target="_blank"
                                rel="noopener"
                                class="avatar avatar-lg flex-shrink-0 overflow-hidden"
                                aria-label="Open {{ $item['type_label'] }}"
                            >
                                @if ($item['is_image'])
                                    <img src="{{ $item['url'] }}" alt="" class="w-100 h-100 object-fit-cover">
                                @elseif ($item['is_video'])
                                    <span class="small fw-bold">VIDEO</span>
                                @else
                                    <span class="small fw-bold">PDF</span>
                                @endif
                            </a>
                            <div class="flex-fill" style="min-width: 0">
                                <div class="fw-medium">
                                    {{ $item['type_label'] }}
                                    @if ($item['is_primary'])
                                        <span class="badge bg-yellow-lt ms-1">Primary</span>
                                    @endif
                                </div>
                                @if (filled($item['caption']))
                                    <div class="small text-secondary">{{ $item['caption'] }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
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
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="workspace-evidence-file">File</label>
                            <input id="workspace-evidence-file" name="file" type="file" required accept="image/*,video/*,application/pdf" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="workspace-evidence-target">Attach to</label>
                            <select
                                id="workspace-evidence-target"
                                name="attachable_id"
                                required
                                class="form-select"
                                onchange="document.getElementById('workspace-evidence-attachable-kind').value = this.options[this.selectedIndex].dataset.kind"
                            >
                                <option value="{{ $repairOrder->id }}" data-kind="repair_order" selected>General (this RO)</option>
                                @foreach ($evidenceConcerns as $concern)
                                    <option value="{{ $concern['id'] }}" data-kind="concern">{{ \Illuminate\Support\Str::limit($concern['summary'], 40) }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="attachable_kind" id="workspace-evidence-attachable-kind" value="repair_order">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="workspace-evidence-caption">Caption</label>
                            <input id="workspace-evidence-caption" name="caption" type="text" maxlength="500" placeholder="What was seen (not a diagnosis)" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-check mb-0">
                                <input type="checkbox" name="as_primary" value="1" class="form-check-input">
                                <span class="form-check-label">Primary</span>
                            </label>
                        </div>
                        <input type="hidden" name="source" value="upload">
                    </div>
                </form>
            @endunless
        </div>

        {{-- Customer / RO Documents - paperwork, not Evidence --}}
        <div data-workspace-modal-panel x-show="task === 'document'" x-cloak>
            @php
                $roDocuments = $customerDocuments ?? collect();
            @endphp

            @unless ($isTerminal)
                @include('operations.documents.partials.add-document-panel-tabler', [
                    'customer' => $repairOrder->customer,
                    'repairOrder' => $repairOrder,
                    'storeUrl' => route('operations.repair-orders.documents.store', $repairOrder),
                    'scanUrl' => route('operations.repair-orders.documents.scan', $repairOrder),
                    'attachUrl' => route('operations.repair-orders.documents.attach', $repairOrder),
                    'attachableDocuments' => $attachableDocuments ?? collect(),
                ])
            @endunless

            @if ($roDocuments->isNotEmpty())
                <div @class(['list-group', 'mt-3' => ! $isTerminal]) data-workspace-document-list>
                    @foreach ($roDocuments as $doc)
                        <div class="list-group-item" x-data="{ emailOpen: false }">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div style="min-width: 0">
                                    <a
                                        href="{{ $doc['viewer_url'] }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="d-block text-truncate fw-bold"
                                    >{{ $doc['title'] }}</a>
                                    <div class="small text-secondary">
                                        {{ $doc['type_label'] }}
                                        @if (($doc['email_send_count'] ?? 0) > 0)
                                            · Emailed {{ $doc['email_send_count'] }}×
                                        @endif
                                    </div>
                                </div>
                                <div class="btn-list flex-nowrap flex-shrink-0">
                                    <a
                                        href="{{ $doc['viewer_url'] }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="btn btn-sm"
                                    >Open</a>
                                    @canany([
                                        \App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value,
                                        \App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value,
                                    ])
                                        <button
                                            type="button"
                                            class="btn btn-sm"
                                            :aria-expanded="emailOpen"
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
                                    'ui' => 'tabler',
                                ])
                            </div>
                        </div>
                    @endforeach
                </div>
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
        <div data-workspace-modal-panel x-show="task === 'review-request' && context.closePaid" x-cloak>
            @include('operations.repair-orders.partials.repair-order-review-request-panel', [
                'repairOrder' => $repairOrder,
                'estimateVersion' => $estimateVersion,
                'reviewRequest' => $reviewRequestModal,
                'closePaid' => true,
                'modal' => true,
            ])
        </div>
        <div data-workspace-modal-panel x-show="task === 'review-request' && ! context.closePaid" x-cloak>
            @include('operations.repair-orders.partials.repair-order-review-request-panel', [
                'repairOrder' => $repairOrder,
                'estimateVersion' => $estimateVersion,
                'reviewRequest' => $reviewRequestModal,
                'closePaid' => false,
                'modal' => true,
            ])
        </div>
    </x-tabler.workspace-modal>
