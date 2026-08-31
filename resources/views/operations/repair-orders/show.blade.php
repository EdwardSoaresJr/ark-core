<x-operations.app title="Repair Order - RO #{{ $repairOrder->repair_order_id }}" :printing="true">
    @php
        $isTerminal = $repairOrder->isTerminal();
        $statusCatalog = app(\App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog::class);
        $lifecycleOptions = app(\App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog::class)
            ->allowedTargetSlugs($repairOrder->status->value, auth()->user());
        $closeVariantOptions = $statusCatalog->allowedCloseVariants($repairOrder->status, auth()->user());
    @endphp
    <script>
        (() => {
            const lineScrollKey = 'ark:repair-order:{{ $repairOrder->repair_order_id }}:line-scroll';
            const concernScrollKey = 'ark:repair-order:{{ $repairOrder->repair_order_id }}:concern-scroll';
            const pendingLineScroll = sessionStorage.getItem(lineScrollKey);
            const pendingConcernScroll = sessionStorage.getItem(concernScrollKey);

            window.preserveRepairOrderLineScroll = (lineId) => {
                const line = document.getElementById(`line-${lineId}`);

                if (! line) {
                    return;
                }

                sessionStorage.setItem(lineScrollKey, JSON.stringify({
                    lineId,
                    top: line.getBoundingClientRect().top,
                }));
            };

            window.preserveRepairOrderConcernScroll = (concernId) => {
                const concern = document.getElementById(`concern-${concernId}`);

                if (! concern) {
                    return;
                }

                sessionStorage.setItem(concernScrollKey, JSON.stringify({
                    concernId,
                    top: concern.getBoundingClientRect().top,
                }));
            };

            const restoreScroll = (pendingScroll, storageKey, idPrefix, idKey) => {
                if (! pendingScroll) {
                    return;
                }

                sessionStorage.removeItem(storageKey);

                requestAnimationFrame(() => {
                    const saved = JSON.parse(pendingScroll);
                    const target = document.getElementById(`${idPrefix}-${saved[idKey]}`);

                    if (! target) {
                        return;
                    }

                    window.scrollBy({
                        top: target.getBoundingClientRect().top - saved.top,
                        left: 0,
                        behavior: 'instant',
                    });
                });
            };

            restoreScroll(pendingLineScroll, lineScrollKey, 'line', 'lineId');
            restoreScroll(pendingConcernScroll, concernScrollKey, 'concern', 'concernId');
        })();

        window.arkPartPricing = (config, matrices, defaultMatrixKey, previewUrl) => ({
            type: config.type || '',
            concernSummary: config.concernSummary || '',
            pricingMode: config.pricingMode || config.defaultPricingMode || 'matrix',
            defaultPricingMode: config.defaultPricingMode || 'matrix',
            defaultPartSell: config.defaultPartSell ?? '',
            matrixKey: config.matrixKey || defaultMatrixKey || (matrices.find((matrix) => matrix.is_default)?.key ?? matrices[0]?.key ?? ''),
            defaultMatrixKey: defaultMatrixKey || (matrices.find((matrix) => matrix.is_default)?.key ?? matrices[0]?.key ?? ''),
            defaultLaborRate: config.defaultLaborRate || '',
            laborCategories: config.laborCategories || [],
            defaultLaborCategoryKey: config.defaultLaborCategoryKey || '',
            laborCategoryKey: config.laborCategoryKey || config.defaultLaborCategoryKey || '',
            laborEnteredHours: config.laborEnteredHours || '1.00',
            laborAdjustment: config.laborAdjustment || 'normal',
            laborCustomFactor: config.laborCustomFactor || '1.25',
            laborReason: config.laborReason || '',
            laborReasonCustom: config.laborReasonCustom || '',
            laborHoursOverridden: Boolean(config.laborHoursOverridden),
            laborFinalHours: config.laborFinalHours || config.laborEnteredHours || '1.00',
            laborOverrideReason: config.laborOverrideReason || '',
            laborRateOverrideReason: config.laborRateOverrideReason || '',
            laborAdjustExpanded: Boolean(config.laborAdjustExpanded),
            laborDescriptionExpanded: Boolean(config.laborDescriptionExpanded),
            explicitMatrix: Boolean(config.explicitMatrix),
            cost: config.cost || '',
            sell: config.sell || '',
            markup: config.markup || '',
            sellEdited: Boolean(config.sellEdited),
            guidance: '',
            marginPercentage: null,
            matrixMarginPercentage: null,
            markupPercentage: null,
            previewTimer: null,
            lineEntryScrollTimer: null,
            previewSequence: 0,
            previewing: false,
            pricingSelection: '',
            hasLineType() {
                return this.type !== '';
            },
            formHasLineType(form) {
                if (this.hasLineType()) {
                    return true;
                }

                const typeInput = form?.querySelector('input[name="type"]');

                return Boolean(typeInput?.value?.trim());
            },
            syncFormLineType(form) {
                if (! form || ! this.type) {
                    return;
                }

                const typeInput = form.querySelector('input[name="type"]');

                if (typeInput) {
                    typeInput.value = this.type;
                }

                // Alpine x-model / x-if can lag one frame behind; force priced fields into FormData.
                const sellInput = form.querySelector('input[name="unit_price"]');

                if (sellInput && this.sell != null && String(this.sell).trim() !== '') {
                    sellInput.value = String(this.sell);
                }

                const costInput = form.querySelector('input[name="part_cost"]');

                if (costInput && this.cost != null) {
                    costInput.value = String(this.cost);
                }
            },
            init() {
                this.$watch('type', (value) => {
                    if (value !== '') {
                        this.scrollLineEntryIntoView();
                    }
                });

                if (! this.hasLineType()) {
                    return;
                }

                if (this.type === 'part' && ! this.explicitMatrix) {
                    this.pricingMode = this.defaultPricingMode;
                    this.matrixKey = this.defaultMatrixKey;
                    if (this.pricingMode === 'manual') {
                        this.pricingSelection = 'manual';
                        if (! this.sell) {
                            this.sell = this.defaultPartSell;
                        }
                    }
                } else if (this.type === 'labor') {
                    this.initializeLaborAuthority();
                    this.onLaborAuthorityInput();
                } else if (this.type === 'sublet') {
                    this.syncSubletMarkupFromCostAndSell();
                    this.queuePreview(0);
                }

                this.pricingSelection = this.pricingMode === 'manual' ? 'manual' : this.matrixKey;
                this.queuePreview(0);
                this.scrollLineEntryIntoView();
                this.$nextTick(() => this.applyPartPullFlagCoupling());
            },
            applyPartPullFlagCoupling() {
                const coreCheckbox = this.$el.querySelector('input[name="has_core"][type="checkbox"]');
                const saveCheckbox = this.$el.querySelector('input[name="save_old_part"][type="checkbox"]');

                if (! coreCheckbox || ! saveCheckbox) {
                    return;
                }

                if (coreCheckbox.checked) {
                    saveCheckbox.checked = true;
                }
            },
            onCoreChargeToggle() {
                this.applyPartPullFlagCoupling();
            },
            onSaveOldPartToggle(event) {
                const coreCheckbox = this.$el.querySelector('input[name="has_core"][type="checkbox"]');

                if (coreCheckbox?.checked && ! event.target.checked) {
                    event.target.checked = true;
                }
            },
            selectLineType(selectedType) {
                this.onTypeChange(selectedType);
            },
            onTypeChange(selectedType = null) {
                if (selectedType !== null) {
                    this.type = selectedType;
                }

                if (this.type !== 'part') {
                    this.pricingMode = 'manual';
                    this.cost = '';
                    this.guidance = '';
                    this.marginPercentage = null;
                    this.matrixMarginPercentage = null;
                    this.markupPercentage = null;
                    this.sellEdited = false;
                    this.markup = '';
                    if (this.type === 'labor') {
                        this.initializeLaborAuthority();
                    } else if (this.type === 'sublet') {
                        this.sell = '';
                    } else if (this.type === 'note') {
                        this.sell = '0';
                    }
                } else if (! this.explicitMatrix) {
                    this.pricingMode = this.defaultPricingMode;
                    this.matrixKey = this.defaultMatrixKey;
                    this.pricingSelection = this.pricingMode === 'manual' ? 'manual' : this.matrixKey;
                    this.explicitMatrix = true;
                    this.sell = this.pricingMode === 'manual' ? this.defaultPartSell : '';
                    this.sellEdited = this.pricingMode === 'manual' && this.defaultPartSell !== '';
                    this.queuePreview(0);
                } else if (! this.pricingMode) {
                    this.pricingMode = this.defaultPricingMode;
                    this.pricingSelection = this.pricingMode === 'manual' ? 'manual' : this.matrixKey;
                }
            },
            scrollLineEntryIntoView() {
                if (! this.hasLineType()) {
                    return;
                }

                window.clearTimeout(this.lineEntryScrollTimer);

                this.lineEntryScrollTimer = window.setTimeout(() => {
                    this.$nextTick(() => {
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                const form = this.$el;
                                const picker = form.querySelector('.ops-line-type-picker');

                                if (! picker) {
                                    return;
                                }

                                const panel = form.querySelector(
                                    this.type === 'part'
                                        ? '[data-line-entry-panel="part"]'
                                        : '[data-line-entry-panel="simple"]',
                                );
                                const padding = 16;
                                const pickerRect = picker.getBoundingClientRect();
                                const panelRect = panel?.getBoundingClientRect();
                                const blockTop = panelRect && panelRect.height > 0
                                    ? panelRect.top
                                    : pickerRect.top;
                                const blockBottom = pickerRect.bottom;
                                const viewportTop = padding;
                                const viewportBottom = window.innerHeight - padding;
                                const blockHeight = blockBottom - blockTop;
                                const maxVisible = window.innerHeight - (padding * 2);
                                let delta = 0;

                                if (blockBottom > viewportBottom) {
                                    delta = blockBottom - viewportBottom;
                                }

                                const topAfter = blockTop - delta;

                                if (topAfter < viewportTop) {
                                    if (blockHeight <= maxVisible) {
                                        delta += topAfter - viewportTop;
                                    } else {
                                        const uplift = viewportTop - topAfter;
                                        const pickerSlack = viewportBottom - (blockBottom - delta);

                                        if (pickerSlack > 0) {
                                            delta -= Math.min(uplift, pickerSlack);
                                        } else if (blockBottom <= viewportBottom) {
                                            delta = blockTop - viewportTop;
                                        }
                                    }
                                }

                                if (Math.abs(delta) > 2) {
                                    window.scrollBy({ top: delta, behavior: 'smooth' });
                                }
                            });
                        });
                    });
                }, 50);
            },
            onCostInput() {
                this.queuePreview(120);
            },
            onSubletCostInput() {
                if (String(this.markup).trim() !== '' && ! this.sellEdited) {
                    this.applySubletMarkupToSell();
                }

                this.queuePreview(120);
            },
            onSubletMarkupInput() {
                this.applySubletMarkupToSell();
                this.queuePreview(120);
            },
            applySubletMarkupToSell() {
                const cost = Number.parseFloat(this.cost);
                const markup = Number.parseFloat(this.markup);

                if (! Number.isFinite(cost) || cost <= 0 || ! Number.isFinite(markup) || markup < 0) {
                    return;
                }

                this.sell = (cost * (1 + markup / 100)).toFixed(2);
                this.sellEdited = false;
            },
            syncSubletMarkupFromCostAndSell() {
                const cost = Number.parseFloat(this.cost);
                const sell = Number.parseFloat(this.sell);

                if (! Number.isFinite(cost) || cost <= 0 || ! Number.isFinite(sell) || sell <= 0) {
                    this.markup = '';

                    return;
                }

                this.markup = (((sell - cost) / cost) * 100).toFixed(2).replace(/\.?0+$/, '');
            },
            subletPricingGuidance() {
                if (this.guidance) {
                    return this.guidance;
                }

                return 'Enter vendor cost, apply markup, or set sell price directly.';
            },
            onCostBlur() {
                this.queuePreview(0);
            },
            onPricingSelectionChange(event) {
                const selection = event?.target?.value ?? this.pricingSelection;
                this.pricingSelection = selection;
                this.pricingMode = selection === 'manual' ? 'manual' : 'matrix';

                if (this.pricingMode === 'matrix') {
                    this.matrixKey = selection;
                    this.explicitMatrix = true;
                    this.sellEdited = false;

                    if (String(this.cost).trim() !== '') {
                        this.sell = '';
                    }
                } else {
                    this.explicitMatrix = true;
                }

                clearTimeout(this.previewTimer);

                if (this.type !== 'part') {
                    return;
                }

                this.$nextTick(() => this.refreshPreview());
            },
            onSellInput() {
                if (this.type === 'labor' && ! this.laborCategoryAllowsModifiers()) {
                    this.sellEdited = false;
                    this.laborRateOverrideReason = '';
                    const category = this.selectedLaborCategory();

                    if (category) {
                        this.sell = (category.rate_cents / 100).toFixed(2);
                    }

                    return;
                }

                this.sellEdited = true;

                if (this.type === 'sublet') {
                    this.syncSubletMarkupFromCostAndSell();
                }

                this.queuePreview();
            },
            usePolicyLaborRate() {
                if (this.type !== 'labor' || ! this.laborCategoryAllowsModifiers()) {
                    return;
                }

                this.sellEdited = false;
                this.laborRateOverrideReason = '';
                this.onLaborCategoryChange(false);
            },
            suggestionText() {
                return this.guidance;
            },
            scopeHint() {
                if (! this.concernSummary) {
                    return '';
                }

                const summary = String(this.concernSummary);
                const trimmed = summary.length > 42 ? `${summary.slice(0, 39)}…` : summary;

                return ` · ${trimmed}`;
            },
            descriptionPlaceholder() {
                const examples = {
                    labor: 'e.g. Diagnose check engine light',
                    part: 'e.g. Oil filter — OEM',
                    note: 'e.g. Customer will decide at next visit',
                    fee: 'e.g. Shop supplies / hazmat',
                    sublet: 'e.g. Alignment at tire shop',
                };
                const labels = {
                    labor: 'Labor description',
                    part: 'Part description',
                    note: 'Note for this scope',
                    fee: 'Fee description',
                    sublet: 'Sublet description',
                };

                return `${labels[this.type] || 'Line description'}${this.scopeHint()} — ${examples[this.type] || 'required'}`;
            },
            descriptionFieldLabel() {
                const labels = {
                    labor: 'Labor description',
                    note: 'Note for this scope',
                    fee: 'Fee description',
                    sublet: 'Sublet description',
                };

                return labels[this.type] || 'Description';
            },
            pricingHeading() {
                const headings = {
                    labor: 'Labor rate',
                    fee: 'Fee amount',
                    sublet: 'Sublet pricing',
                };

                return headings[this.type] || 'Pricing';
            },
            sellFieldLabel() {
                if (this.type === 'labor') {
                    return 'Labor rate';
                }

                if (this.type === 'sublet') {
                    return 'Sublet rate';
                }

                return 'Amount';
            },
            quantityFieldLabel() {
                if (this.type === 'labor') {
                    return 'Hours';
                }

                return 'Quantity';
            },
            addLineButtonLabel() {
                const labels = {
                    labor: 'Add Labor',
                    note: 'Add Note',
                    fee: 'Add Fee',
                    sublet: 'Add Sublet',
                };

                return labels[this.type] || 'Add';
            },
            selectedLaborCategory() {
                return this.laborCategories.find((category) => category.key === this.laborCategoryKey)
                    || this.laborCategories[0]
                    || null;
            },
            laborCategoryAllowsModifiers() {
                const category = this.selectedLaborCategory();

                return category ? category.allows_modifiers !== false : true;
            },
            clearLaborModifiersIfLocked() {
                if (this.laborCategoryAllowsModifiers()) {
                    return;
                }

                this.laborAdjustment = 'normal';
                this.laborReason = '';
                this.laborReasonCustom = '';
                this.laborHoursOverridden = false;
                this.laborOverrideReason = '';
                this.laborRateOverrideReason = '';
                this.laborAdjustExpanded = false;
                this.sellEdited = false;

                const category = this.selectedLaborCategory();

                if (category) {
                    this.sell = (category.rate_cents / 100).toFixed(2);
                }

                this.onLaborAuthorityInput();
            },
            laborAdjustmentFactor() {
                const factors = {
                    normal: 1,
                    difficult: 1.25,
                    severe: 1.5,
                };

                if (this.laborAdjustment === 'custom') {
                    const custom = Number.parseFloat(this.laborCustomFactor);

                    return Number.isFinite(custom) && custom >= 1 ? custom : 1;
                }

                return factors[this.laborAdjustment] ?? 1;
            },
            laborRoundingIncrement(rule) {
                const increments = {
                    exact: null,
                    tenth: 0.1,
                    quarter: 0.25,
                    half: 0.5,
                };

                return increments[rule] ?? 0.25;
            },
            laborRoundingLabel(rule) {
                const labels = {
                    exact: 'exact hours',
                    tenth: '0.1 hr',
                    quarter: '0.25 hr',
                    half: '0.5 hr',
                };

                return labels[rule] ?? '0.25 hr';
            },
            roundLaborHours(hours, rule) {
                if (rule === 'exact') {
                    return Math.round(hours * 100) / 100;
                }

                const increment = this.laborRoundingIncrement(rule);

                return Math.ceil((hours / increment) - 1e-9) * increment;
            },
            laborCalculatedHours() {
                const category = this.selectedLaborCategory();

                if (! category) {
                    return Number.parseFloat(this.laborEnteredHours) || 0;
                }

                const entered = Number.parseFloat(this.laborEnteredHours) || 0;
                const adjusted = entered * this.laborAdjustmentFactor();
                const minimum = Number.parseFloat(category.minimum_hours) || 0;
                const afterMinimum = Math.max(adjusted, minimum);

                return this.roundLaborHours(afterMinimum, category.rounding_rule);
            },
            laborNeedsAdvancedPanel() {
                if (! this.laborCategoryAllowsModifiers()) {
                    return false;
                }

                return this.laborAdjustment !== 'normal'
                    || this.laborHoursOverridden
                    || Boolean(this.laborOverrideReason);
            },
            toggleLaborAdjustExpanded() {
                if (! this.laborCategoryAllowsModifiers()) {
                    return;
                }

                if (this.laborAdjustExpanded) {
                    this.laborAdjustment = 'normal';
                    this.laborReason = '';
                    this.laborReasonCustom = '';
                    this.onLaborAuthorityInput();
                }

                this.laborAdjustExpanded = ! this.laborAdjustExpanded;
            },
            laborCanBillEnteredHours() {
                if (! this.laborCategoryAllowsModifiers()) {
                    return false;
                }

                const entered = Number.parseFloat(this.laborEnteredHours) || 0;
                const final = Number.parseFloat(this.laborFinalHours) || 0;

                return Math.abs(entered - final) >= 0.005;
            },
            billEnteredHours() {
                this.laborFinalHours = (Number.parseFloat(this.laborEnteredHours) || 0).toFixed(2);
                this.onLaborFinalHoursInput();
            },
            useCalculatedLaborHours() {
                this.laborHoursOverridden = false;
                this.laborOverrideReason = '';
                this.onLaborAuthorityInput();
            },
            laborMinimumApplied() {
                const category = this.selectedLaborCategory();

                if (! category) {
                    return false;
                }

                const entered = Number.parseFloat(this.laborEnteredHours) || 0;
                const adjusted = entered * this.laborAdjustmentFactor();
                const minimum = Number.parseFloat(category.minimum_hours) || 0;

                return adjusted < minimum && minimum > 0;
            },
            laborMinimumMessage() {
                if (this.type !== 'labor' || ! this.laborMinimumApplied()) {
                    return '';
                }

                const category = this.selectedLaborCategory();
                const entered = Number.parseFloat(this.laborEnteredHours) || 0;
                const calculated = this.laborCalculatedHours();

                return `${category.name} minimum applied: ${entered.toFixed(2)} hr → ${calculated.toFixed(2)} hr`;
            },
            laborRoundingMessage() {
                if (this.type !== 'labor' || this.laborHoursOverridden || this.laborMinimumApplied()) {
                    return '';
                }

                const category = this.selectedLaborCategory();

                if (! category || category.rounding_rule === 'exact') {
                    return '';
                }

                const entered = Number.parseFloat(this.laborEnteredHours) || 0;
                const adjusted = entered * this.laborAdjustmentFactor();
                const minimum = Number.parseFloat(category.minimum_hours) || 0;
                const afterMinimum = Math.max(adjusted, minimum);
                const calculated = this.laborCalculatedHours();

                if (Math.abs(afterMinimum - calculated) < 0.005) {
                    return '';
                }

                return `Rounded up to ${this.laborRoundingLabel(category.rounding_rule)}: ${afterMinimum.toFixed(2)} hr → ${calculated.toFixed(2)} hr`;
            },
            initializeLaborAuthority() {
                if (! this.laborCategoryKey && this.defaultLaborCategoryKey) {
                    this.laborCategoryKey = this.defaultLaborCategoryKey;
                }

                if (! this.laborEnteredHours) {
                    this.laborEnteredHours = '1.00';
                }

                if (! this.laborAdjustExpanded && this.laborNeedsAdvancedPanel()) {
                    this.laborAdjustExpanded = true;
                }

                this.clearLaborModifiersIfLocked();

                if (! this.sellEdited) {
                    this.onLaborCategoryChange(false);
                } else {
                    this.onLaborAuthorityInput();
                }
            },
            onLaborCategoryChange(recalculate = true) {
                const category = this.selectedLaborCategory();

                if (category) {
                    this.sell = (category.rate_cents / 100).toFixed(2);
                    this.sellEdited = false;
                    this.laborRateOverrideReason = '';
                }

                this.clearLaborModifiersIfLocked();

                if (recalculate) {
                    this.onLaborAuthorityInput();
                }
            },
            onLaborAuthorityInput() {
                if (! this.laborHoursOverridden) {
                    this.laborFinalHours = this.laborCalculatedHours().toFixed(2);
                }
            },
            onLaborFinalHoursInput() {
                const calculated = this.laborCalculatedHours().toFixed(2);

                this.laborHoursOverridden = this.laborFinalHours !== calculated;
            },
            laborAdjustmentRequiresReason() {
                return this.laborAdjustment !== 'normal';
            },
            laborAuthorityPreview() {
                const category = this.selectedLaborCategory();

                if (! category || this.type !== 'labor') {
                    return '';
                }

                const calculated = this.laborCalculatedHours().toFixed(2);
                const adjustmentLabel = {
                    normal: 'Normal',
                    difficult: 'Difficult (+25%)',
                    severe: 'Severe (+50%)',
                    custom: `Custom (×${this.laborAdjustmentFactor().toFixed(2)})`,
                }[this.laborAdjustment] || 'Normal';

                let preview = `Book ${this.laborEnteredHours} hr · ${category.name} · ${adjustmentLabel} · Calculated ${calculated} hr`;

                if (this.laborHoursOverridden) {
                    preview += ` · Advisor override ${this.laborFinalHours} hr`;
                }

                if (this.sellEdited) {
                    preview += ` · Custom rate $${this.sell}/hr`;
                }

                return preview;
            },
            simpleLineGuidance() {
                const guidance = {
                    labor: 'Book hours are what you quote. Billable hours apply category minimums and rounding — edit billable hours directly or use Bill book hours.',
                    fee: 'Flat fee or per-quantity shop charge on the estimate.',
                    sublet: 'Vendor work billed through this scope — enter cost, markup, or sell price. Rolls into labor totals.',
                };

                return guidance[this.type] || '';
            },
            quantityPlaceholder() {
                if (this.type === 'labor') {
                    return 'Hours';
                }

                return 'Qty';
            },
            sellPlaceholder() {
                if (this.type === 'part' && this.pricingMode === 'matrix') {
                    return 'Matrix sell $';
                }

                if (this.type === 'labor') {
                    return 'Labor rate $';
                }

                if (this.type === 'sublet') {
                    return 'Sell price $';
                }

                if (this.type === 'fee') {
                    return 'Fee amount $';
                }

                if (this.type === 'note') {
                    return 'No charge';
                }

                return 'Sell $';
            },
            costPlaceholder() {
                return 'Your cost $';
            },
            vendorPlaceholder() {
                return 'Vendor — e.g. Worldpac, NAPA';
            },
            partNumberPlaceholder() {
                return 'Manufacturer part #';
            },
            sourcingPlaceholder() {
                return 'Source, ETA, or order note';
            },
            queuePreview(delay = 200) {
                clearTimeout(this.previewTimer);

                if (this.type !== 'part' && this.type !== 'sublet') {
                    return;
                }

                this.previewTimer = setTimeout(() => this.refreshPreview(), delay);
            },
            applyMatrixSellFromServer(payload) {
                if (this.pricingMode !== 'matrix' || this.sellEdited) {
                    return;
                }

                if (payload.sell_from_matrix != null && payload.sell_from_matrix !== '') {
                    this.sell = payload.sell_from_matrix;

                    return;
                }

                if (payload.suggested_sell) {
                    this.sell = payload.suggested_sell.replace('$', '').replace(/,/g, '');

                    return;
                }

                if (! String(this.cost).trim()) {
                    this.sell = '';
                }
            },
            worksheetFormFrom(eventOrForm) {
                if (eventOrForm instanceof HTMLFormElement) {
                    return eventOrForm;
                }

                return eventOrForm?.target?.closest?.('form') ?? eventOrForm?.currentTarget ?? null;
            },
            async invokeWorksheetSubmit(form) {
                const worksheet = this.$el.closest('[data-worksheet-root]')?._x_dataStack?.[0];

                if (typeof worksheet?.submitWorksheetForm === 'function') {
                    await worksheet.submitWorksheetForm(form);

                    return;
                }

                if (typeof this.$parent?.submitWorksheetForm === 'function') {
                    await this.$parent.submitWorksheetForm(form);

                    return;
                }

                form?.submit();
            },
            async submitLine(event) {
                const worksheet = this.$el.closest('[data-worksheet-root]')?._x_dataStack?.[0];

                if (worksheet?.worksheetBusyPending || worksheet?.worksheetSaving) {
                    worksheet?.surfaceWorksheetMessage?.(
                        'Still saving the last change. Wait a moment, then try again.',
                    );

                    return;
                }

                const form = this.worksheetFormFrom(event);

                if (! form || ! this.formHasLineType(form)) {
                    worksheet?.surfaceWorksheetMessage?.(
                        'Choose a line type (labor, part, fee, sublet, or note) before adding.',
                    );

                    return;
                }

                this.syncFormLineType(form);

                if (this.type === 'labor' && this.laborCategoryAllowsModifiers() && this.sellEdited) {
                    const reason = String(this.laborRateOverrideReason ?? '').trim();

                    if (reason === '') {
                        const modal = document.querySelector('#workspace-modal-host')?._x_dataStack?.[0];

                        if (modal) {
                            modal.validationMessage = 'Custom labor rate needs a reason — choose Menu / package price for a flat PPI, or another reason.';
                            modal.saving = false;
                        }

                        worksheet?.surfaceWorksheetMessage?.(
                            'Choose a rate override reason before saving custom labor.',
                            { tone: 'warn' },
                        );
                        this.$nextTick(() => form.querySelector('[name="labor_rate_override_reason"]')?.focus());

                        return;
                    }
                }

                if (this.type === 'part') {
                    this.explicitMatrix = true;
                }

                if (this.type === 'part' && this.pricingMode === 'matrix' && String(this.cost).trim() !== '' && ! this.sellEdited) {
                    try {
                        await Promise.race([
                            this.refreshPreview(),
                            new Promise((resolve) => setTimeout(resolve, 4000)),
                        ]);
                    } catch {
                        // Server prices the line authoritatively on save.
                    }
                }

                await this.invokeWorksheetSubmit(form);
            },
            async refreshPreview() {
                const requestId = ++this.previewSequence;
                this.previewing = true;
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 8000);

                try {
                    const params = {
                        type: this.type,
                        part_cost: this.cost,
                        pricing_mode: this.pricingMode,
                        pricing_matrix_key: this.matrixKey,
                        pricing_matrix_explicit: this.explicitMatrix ? '1' : '0',
                        unit_price_override: this.sellEdited ? '1' : '0',
                    };

                    if (this.sellEdited || this.pricingMode === 'manual') {
                        params.unit_price = this.sell;
                    }

                    const response = await fetch(previewUrl, {
                        method: 'POST',
                        body: new URLSearchParams(params),
                        signal: controller.signal,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        },
                    });

                    if (! response.ok) {
                        this.guidance = 'Server pricing guidance is unavailable.';

                        return;
                    }

                    const payload = await response.json();

                    if (requestId !== this.previewSequence) {
                        return;
                    }

                    this.guidance = payload.guidance || '';
                    this.marginPercentage = payload.margin_percentage;
                    this.matrixMarginPercentage = payload.matrix_margin_percentage;
                    this.markupPercentage = payload.markup_percentage;
                    this.applyMatrixSellFromServer(payload);

                    if (this.type === 'sublet' && ! this.sellEdited && payload.markup_percentage) {
                        this.markup = payload.markup_percentage;
                    }
                } catch {
                    this.guidance = 'Server pricing guidance is unavailable.';
                } finally {
                    clearTimeout(timeoutId);

                    if (requestId === this.previewSequence) {
                        this.previewing = false;
                    }
                }
            },
        });

    </script>

    @php
        $repairOrder->loadMissing('customer');
        $shopSettings = App\Ark\Operations\Settings\ShopSettings::current();
        $defaultPartsMatrixKey = $shopSettings->defaultPartsMatrix()['key'];
        $laborGuideConcernId = $repairOrder->concerns->count() === 1
            ? $repairOrder->concerns->first()->id
            : null;
        $rteLaborGuide = app(App\Ark\Operations\LaborGuides\Rte\RteLaborGuideContext::class)
            ->forRepairOrder($repairOrder, $laborGuideConcernId);
        $rteLaborConcerns = $repairOrder->concerns
            ->map(fn ($concern): array => [
                'id' => $concern->id,
                'summary' => $concern->summary,
            ])
            ->values()
            ->all();
    @endphp
    <section
        data-worksheet-root
        @if (session('ark_line_id')) data-ark-line-id="{{ session('ark_line_id') }}" @endif
        x-data="Object.assign(arkWorksheetCollaboration({
            repairOrderId: @js($repairOrder->repair_order_id),
            estimateVersion: @js($estimateVersion),
            currentUserId: @js(auth()->id()),
            estimateVersionField: @js(App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD),
            surface: @js($worksheetSurface),
            conflictFragment: 'estimate-lines',
            heartbeatUrl: @js(route('operations.repair-orders.worksheet-sessions.heartbeat', $repairOrder)),
            releaseUrl: @js(route('operations.repair-orders.worksheet-sessions.release', $repairOrder)),
            broadcastEnabled: @js(App\Ark\Operations\RepairOrders\RepairOrderEstimateBroadcast::enabled()),
            broadcastChannel: @js(App\Ark\Operations\RepairOrders\RepairOrderEstimateBroadcast::channelName($repairOrder->repair_order_id)),
        }), arkWorksheetContinuity({
            worksheetScopeId: 'estimate-lines',
            continuityPanelIds: ['worksheet-status-flash', 'ro-identity-band', 'visit-reason', 'estimate-builder-rail', 'estimate-total-panel', 'review-toolbar', 'workspace-modal-host', 'ro-orientation-header'],
            conflictFragment: 'estimate-lines',
            refreshScopeMap: {
                worksheet: 'estimate-lines',
                rail: 'estimate-builder-rail',
                toolbar: 'review-toolbar',
            },
        }), {
            partsMatrices: @js($partsMatrices),
            defaultPartsMatrixKey: @js($defaultPartsMatrixKey),
            laborGuideNotice: '',
            clearLaborGuideNotice() {
                this.laborGuideNotice = '';
            },
            focusCreateScope() {
                window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', {
                    detail: {
                        task: 'add-work',
                        invokeEl: document.querySelector('[data-workspace-modal-trigger=add-work]'),
                    },
                }));
            },
            openLaborGuideFromTrigger(trigger) {
                if (! trigger?.dataset?.laborGuide) {
                    return;
                }

                let payload = {};

                try {
                    payload = JSON.parse(trigger.dataset.laborGuide);
                } catch {
                    return;
                }

                this.openLaborGuide(
                    payload.url || null,
                    payload.vin || null,
                    payload.notice || null,
                    payload.windowName || null,
                );
            },
            openLaborGuide(url, vin, notice, windowName) {
                if (! url) {
                    return;
                }

                const clipboardVin = typeof vin === 'string' ? vin.trim() : '';

                if (clipboardVin !== '') {
                    try {
                        navigator.clipboard.writeText(clipboardVin);
                    } catch {
                        // Clipboard is optional; launch still proceeds.
                    }
                }

                if (notice) {
                    window.dispatchEvent(new CustomEvent('ark:labor-guide-notice', {
                        detail: { message: notice },
                    }));
                }

                window.open(url, windowName || 'ark-labor-guide', 'noopener,noreferrer');
            },
            rteLabor: window.arkRteLaborGuide(@js([
                'available' => $rteLaborGuide['available'],
                'blockedReason' => $rteLaborGuide['blocked_reason'],
                'vehicleLabel' => $rteLaborGuide['vehicle_label'],
                'vehicleEngineLabel' => $rteLaborGuide['vehicle_engine_label'] ?? null,
                'modelYear' => $rteLaborGuide['model_year'],
                'defaultCarIdCode' => $rteLaborGuide['default_car_id_code'],
                'carCandidates' => $rteLaborGuide['car_candidates'],
                'defaultConcernId' => $laborGuideConcernId ?? ($rteLaborConcerns[0]['id'] ?? null),
                'concerns' => $rteLaborConcerns,
                'searchUrl' => route('operations.repair-orders.rte-labor.search', $repairOrder),
                'applyUrl' => route('operations.repair-orders.rte-labor.apply', $repairOrder),
                'estimateVersionField' => App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD,
                'estimateVersion' => $estimateVersion,
                'defaultHoursBasis' => App\Ark\Operations\LaborGuides\Rte\RteLaborHoursBasis::default()->value,
            ])),
            openRteLaborGuide() {
                this.rteLabor.openPanel();
            },
        })"
        x-init="initWorksheetCollaboration()"
        class="ops-estimate-workspace ops-estimate-workspace--builder"
        @ark:labor-guide-notice.window="laborGuideNotice = $event.detail.message || ''"
    >
        @include('operations.repair-orders.partials.worksheet-busy-overlay')

        <div id="worksheet-status-flash">
            @if (session('error'))
                <div class="border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-900">
                    {{ session('error') }}
                </div>
            @endif

            @if (session('status'))
                @php
                    $worksheetStatus = (string) session('status');
                    $worksheetStatusSaved = strcasecmp($worksheetStatus, 'Saved') === 0;
                @endphp
                <div
                    data-worksheet-server-status
                    class="{{ $worksheetStatusSaved
                        ? 'border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950'
                        : 'border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-950' }}"
                >
                    {{ $worksheetStatus }}
                </div>
            @endif

            @if (session('worksheet_focus_concern_id'))
                <span data-worksheet-focus-concern="{{ session('worksheet_focus_concern_id') }}" hidden></span>
            @endif

            @if ($errors->has('lifecycle'))
                <div class="border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-900">
                    {{ $errors->first('lifecycle') }}
                </div>
            @endif
        </div>

        <div x-show="laborGuideNotice" x-cloak class="border border-sky-300 bg-sky-50 px-3 py-2 text-sm font-semibold text-sky-950">
            <div class="flex items-start justify-between gap-3">
                <p x-text="laborGuideNotice"></p>
                <button type="button" class="text-xs font-bold uppercase tracking-[0.08em] text-sky-800 hover:text-sky-950" @click="clearLaborGuideNotice()">Dismiss</button>
            </div>
        </div>

        @include('operations.repair-orders.partials.repair-order-worksheet-collaboration-banners')

        @include('operations.repair-orders.partials.repair-order-intake-context-band', [
            'repairOrder' => $repairOrder,
        ])

        <div class="ops-review-shell">
            @include('operations.repair-orders.partials.operational-identity-band', [
                'repairOrder' => $repairOrder,
                'identityVariant' => 'staff',
                'estimateVersion' => $estimateVersion,
                'totals' => $totals,
            ])

            <div id="review-toolbar" class="ops-review-actions">
                <div class="ops-review-toolbar">
                    @include('operations.repair-orders.partials.repair-order-estimate-toolbar-actions', [
                        'repairOrder' => $repairOrder,
                        'mode' => 'edit',
                        'isTerminal' => $isTerminal,
                        'showConcernStore' => false,
                        'laborGuideConcernId' => $laborGuideConcernId,
                        'rteLaborGuide' => $rteLaborGuide,
                    ])

                    @include('operations.repair-orders.partials.repair-order-toolbar-visit-signals', [
                        'repairOrder' => $repairOrder,
                    ])

                    @unless ($isTerminal)
                        @if ($canAuthorRepairOrder ?? false)
                            <div class="ops-review-toolbar-section">
                                <div class="ops-review-toolbar-row">
                                    <button
                                        type="button"
                                        class="ops-review-action"
                                        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'review-estimate-notes', context: {}, invokeEl: $event.currentTarget } }))"
                                    >
                                        Review Estimate Notes
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endunless

                    @include('operations.repair-orders.partials.repair-order-toolbar-print-slot', [
                        'repairOrder' => $repairOrder,
                        'financial' => $financial,
                        'customerDocumentsCount' => ($customerDocuments ?? collect())->count(),
                        'canAuthorRepairOrder' => $canAuthorRepairOrder ?? false,
                        'isTerminal' => $isTerminal,
                    ])

                    @include('operations.repair-orders.partials.repair-order-estimate-toolbar-workflow', [
                        'repairOrder' => $repairOrder,
                        'isTerminal' => $isTerminal,
                        'lifecycleOptions' => $lifecycleOptions,
                        'closeVariantOptions' => $closeVariantOptions,
                        'technicians' => $technicians,
                        'soloOwnerShop' => $soloOwnerShop,
                        'estimateVersion' => $estimateVersion,
                        'financial' => $financial,
                        'balanceProjection' => $balanceProjection ?? null,
                        'mode' => 'edit',
                    ])

                    @include('operations.repair-orders.partials.repair-order-toolbar-mode-slot', [
                        'repairOrder' => $repairOrder,
                        'mode' => 'edit',
                        'isTerminal' => $isTerminal,
                        'registerModeShortcut' => false,
                    ])
                </div>

            </div>
        </div>

        <div class="ops-estimate-layout">
            <div class="ops-estimate-main min-w-0">
                <x-operations.repair-order-workspace-tabs
                    workspaceMode="review"
                    :repairOrder="$repairOrder"
                    :totals="$totals"
                    :estimateVersion="$estimateVersion"
                    :isTerminal="$isTerminal"
                    :partsBlockingCount="$partsBlockingCount"
                    :partsReadinessCounts="$partsReadinessCounts"
                    :approvedConcerns="$approvedConcerns"
                    :priorVehicleFutureWorkCount="$priorVehicleFutureWorkCount"
                    :recordedFindingCount="$recordedFindingCount ?? 0"
                >
                @include('operations.repair-orders.partials.repair-order-visit-reason', [
                    'repairOrder' => $repairOrder,
                    'isTerminal' => $isTerminal,
                    'estimateVersion' => $estimateVersion,
                ])

                <div id="estimate-lines" class="scroll-mt-6" :class="worksheetBusyPending ? 'ops-worksheet-saving' : ''">
                    <div class="ops-estimate-instruments-shell">
                        @include('operations.repair-orders.partials.repair-order-estimate-workspace-header', [
                            'repairOrder' => $repairOrder,
                            'totals' => $totals,
                        ])
                    </div>

                    @if (($engineOilServices ?? collect())->isNotEmpty())
                        @include('operations.maintenance.partials.engine-oil-panel', [
                            'repairOrder' => $repairOrder,
                            'engineOilServices' => $engineOilServices ?? collect(),
                            'estimateVersion' => $estimateVersion,
                            'presentationOnly' => true,
                        ])
                    @endif

                    @if (($testingAuthorizations ?? collect())->isNotEmpty())
                        @include('operations.work-authorization.partials.testing-package-panel', [
                            'repairOrder' => $repairOrder,
                            'testingAuthorizations' => $testingAuthorizations ?? collect(),
                            'estimateVersion' => $estimateVersion,
                            'isTerminal' => $isTerminal,
                            'presentationOnly' => true,
                        ])
                    @endif

                    @php
                        $evidenceItemsCount = ($evidenceGallery['items'] ?? collect())->count();
                    @endphp
                    @if ($evidenceItemsCount > 0)
                        <div class="ops-builder-evidence-entry mb-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="ops-builder-evidence-entry__btn"
                                data-workspace-modal-trigger="evidence"
                                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'evidence', invokeEl: $event.currentTarget } }))"
                            >
                                Photos ({{ $evidenceItemsCount }})
                            </button>
                        </div>
                    @endif

                    @unless ($isTerminal)
                        {{-- Add Work lives on the contextual footer — one primary CTA per viewport. --}}
                    @endunless

                    @include('operations.repair-orders.partials.workspace-modal.host', [
                        'repairOrder' => $repairOrder,
                        'isTerminal' => $isTerminal,
                        'estimateVersion' => $estimateVersion,
                        'editingLineId' => $editingLineId,
                        'engineOilServices' => $engineOilServices ?? collect(),
                        'testingAuthorizations' => $testingAuthorizations ?? collect(),
                        'evidenceGallery' => $evidenceGallery ?? ['items' => collect(), 'concerns' => collect()],
                        'customerDocuments' => $customerDocuments ?? collect(),
                        'attachableDocuments' => $attachableDocuments ?? collect(),
                        'totals' => $totals,
                        'partsMatrices' => $partsMatrices,
                        'laborCategories' => $laborCategories,
                        'defaultLaborRate' => $defaultLaborRate,
                        'defaultNotesPrivate' => $defaultNotesPrivate,
                        'shopSettings' => $shopSettings,
                        'technicians' => $technicians ?? collect(),
                    ])

                    <div class="ops-worksheet-concerns">
                        @if (! $isTerminal)
                            @include('operations.repair-orders.partials.repair-order-dealer-quote-capture', [
                                'repairOrder' => $repairOrder,
                                'estimateVersion' => $estimateVersion,
                            ])
                        @endif

                        @php
                            // Priority order (Diagnostic → … → Plan Soon), then advisor position.
                            // Each concern carries its own priority badge — no group wrappers.
                            $worksheetConcerns = App\Ark\Operations\RepairOrders\RecommendationIntent::sortedModels($repairOrder->concerns);
                        @endphp
                        @forelse ($worksheetConcerns as $concern)
                            @php
                                $concernPartsMatrixKey = $concern->billing_posture
                                    ->defaultPartsMatrix($shopSettings)['key'];
                                $concernLaborDefaults = $shopSettings->laborDefaultsForConcern(
                                    $concern->billing_posture,
                                    $repairOrder->customer,
                                );
                                $concernDefaultLaborRate = $concernLaborDefaults['rate'];
                                $concernDefaultLaborCategoryKey = $concernLaborDefaults['category_key'];
                            @endphp
                            <section id="concern-{{ $concern->id }}" class="ops-worksheet-concern {{ $concern->recommendationIntent()->worksheetScopeClass() }} ops-worksheet-concern--{{ $concern->disposition->value }} scroll-mt-24">
                                <x-operations.scope-header
                                    :title="$concern->summary"
                                    :total="$totals->format($totals->concernSubtotalCents($concern->id))"
                                    :eyebrow="($isTerminal || ! $concern->shouldSurfaceRecommendationStatus())
                                        ? $concern->recommendationIntent()->staffLabel()
                                        : null"
                                    :eyebrow-class="$concern->recommendationIntent()->intentLabelClass()"
                                >
                                    <x-slot:subline>
                                        @include('operations.repair-orders.partials.repair-order-concern-meaning-subline', [
                                            'concern' => $concern,
                                        ])
                                    </x-slot:subline>
                                    <x-slot:status>
                                        @include('operations.repair-orders.partials.repair-order-concern-disposition-decision', [
                                            'concern' => $concern,
                                        ])
                                    </x-slot:status>
                                    @unless ($isTerminal)
                                        <x-slot:toolbar>
                                            @include('operations.repair-orders.partials.repair-order-concern-disposition-control', [
                                                'repairOrder' => $repairOrder,
                                                'concern' => $concern,
                                                'isTerminal' => $isTerminal,
                                                'estimateVersion' => $estimateVersion,
                                                'authorViaModal' => (bool) ($canAuthorRepairOrder ?? false),
                                            ])
                                            @include('operations.repair-orders.partials.repair-order-concern-production-status-control', [
                                                'repairOrder' => $repairOrder,
                                                'concern' => $concern,
                                                'isTerminal' => $isTerminal,
                                                'estimateVersion' => $estimateVersion,
                                                'authorViaModal' => (bool) ($canAuthorRepairOrder ?? false),
                                            ])
                                            @include('operations.repair-orders.partials.repair-order-concern-scope-settings', [
                                                'repairOrder' => $repairOrder,
                                                'concern' => $concern,
                                                'isTerminal' => $isTerminal,
                                                'estimateVersion' => $estimateVersion,
                                                'concernDefaultLaborRate' => $concernDefaultLaborRate,
                                                'canMoveScopeToNewRo' => ! ($financial['hasIssuedInvoice'] ?? false),
                                                'authorViaModal' => (bool) ($canAuthorRepairOrder ?? false),
                                            ])
                                        </x-slot:toolbar>
                                    @endunless
                                </x-operations.scope-header>

                                @include('operations.repair-orders.partials.repair-order-concern-narrative-card', [
                                    'repairOrder' => $repairOrder,
                                    'concern' => $concern,
                                    'isTerminal' => $isTerminal,
                                    'estimateVersion' => $estimateVersion,
                                ])

                                @include('operations.repair-orders.partials.repair-order-concern-work-section', [
                                    'repairOrder' => $repairOrder,
                                    'concern' => $concern,
                                    'isTerminal' => $isTerminal,
                                    'editingLineId' => $editingLineId,
                                    'totals' => $totals,
                                    'taxLabel' => $taxLabel,
                                    'estimateVersion' => $estimateVersion,
                                    'concernDefaultLaborRate' => $concernDefaultLaborRate,
                                    'concernDefaultLaborCategoryKey' => $concernDefaultLaborCategoryKey,
                                    'concernPartsMatrixKey' => $concernPartsMatrixKey,
                                    'partsMatrices' => $partsMatrices,
                                    'laborCategories' => $laborCategories,
                                    'defaultLaborRate' => $defaultLaborRate,
                                    'defaultNotesPrivate' => $defaultNotesPrivate,
                                    'technicians' => $technicians ?? collect(),
                                ])
                            </section>
                        @empty
                            @if ($isTerminal)
                                <div class="rounded-sm border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-600">No scopes on this estimate.</div>
                            @endif
                        @endforelse

                    </div>
                </div>
                </x-operations.repair-order-workspace-tabs>
            </div>

            <aside id="estimate-builder-rail" class="ops-review-rail ops-review-rail--pinned">
                <x-operations.estimate-totals-panel
                    id="estimate-total-panel"
                    class="ops-review-rail-totals-pinned"
                    :totals="$totals"
                    :tax-label="$taxLabel"
                    :financial="$financial['showFinancialRail'] ? $financial : null"
                    :repair-order="$repairOrder"
                    :approval-forecast="$approvalForecast ?? null"
                >
                    @if ($financial['showFinancialRail'])
                        @include('operations.repair-orders.partials.financial-payment-strip', [
                            'repairOrder' => $repairOrder,
                            'financial' => $financial,
                            'estimateVersion' => $estimateVersion,
                        ])
                    @else
                        <p class="text-xs leading-5 text-slate-600">
                            {{ session('status') ? session('status').' Totals refreshed.' : ($repairOrder->lines->isNotEmpty() ? 'Review concerns and line totals before approval.' : 'Add at least one estimate line before approval review.') }}
                        </p>
                    @endif
                </x-operations.estimate-totals-panel>

                <div class="ops-review-rail__scroll">
                    @include('operations.repair-orders.partials.repair-order-rail-posture', [
                        'postureLayout' => 'rail',
                        'repairOrder' => $repairOrder,
                        'nextAction' => $nextAction ?? null,
                        'approvalPosture' => $approvalPosture ?? null,
                        'approvedConcerns' => $approvedConcerns ?? collect(),
                        'deferredConcerns' => $deferredConcerns ?? collect(),
                        'recommendedConcerns' => $recommendedConcerns ?? collect(),
                        'lastApprovalEvent' => $lastApprovalEvent ?? null,
                        'financial' => $financial ?? [],
                        'partsBlockingCount' => $partsBlockingCount ?? 0,
                    ])

                    @include('operations.repair-orders.partials.operational-journey-card', [
                        'operationalJourney' => $operationalJourney ?? null,
                        'journeyComparison' => $journeyComparison ?? null,
                    ])

                    @if ($financial['showFinancialRail'])
                        @include('operations.repair-orders.partials.financial-rail')
                    @endif

                    @include('operations.repair-orders.partials.repair-order-lifecycle-panel')

                    @include('operations.work.partials.advisor-work-context-panel', [
                        'followUps' => $openFollowUps ?? [],
                        'tasks' => $openTasks ?? [],
                    ])
                </div>
            </aside>
        </div>

        @include('operations.repair-orders.partials.repair-order-rte-labor-panel', [
            'rteLaborGuide' => $rteLaborGuide,
            'rteLaborConcerns' => $rteLaborConcerns,
        ])

        @include('operations.repair-orders.partials.repair-order-orientation-header', [
            'repairOrder' => $repairOrder,
            'currentSituation' => $currentSituation ?? null,
            'workspaceStrip' => $workspaceStrip,
            'repairOrderFooter' => $repairOrderFooter ?? null,
            'nextAction' => $nextAction ?? null,
            'approvalPosture' => $approvalPosture ?? null,
            'approvedConcerns' => $approvedConcerns ?? null,
            'deferredConcerns' => $deferredConcerns ?? null,
            'recommendedConcerns' => $recommendedConcerns ?? null,
            'lastApprovalEvent' => $lastApprovalEvent ?? null,
        ])
    </section>
</x-operations.app>
