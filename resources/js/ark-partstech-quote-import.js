export function arkPartsTechQuoteImport(config) {
    return {
        previewUrl: config.previewUrl,
        importUrl: config.importUrl,
        pricingPreviewUrl: config.pricingPreviewUrl,
        csrfToken: config.csrfToken || '',
        poNumber: config.poNumber,
        partsMatrices: normalizeMatrices(config.initialPartsMatrices ?? []),
        concerns: normalizeConcerns(config.initialConcerns ?? []),
        defaultConcernId: concernIdString(config.defaultConcernId),
        defaultPartsMatrixKey: String(config.defaultPartsMatrixKey ?? ''),
        rows: [],
        loaded: false,
        loading: false,
        loadingStatus: 'Pulling quote from PartsTech…',
        importing: false,
        error: '',
        cartLocked: false,
        syncingMatrices: false,

        init() {
            this.applyQuoteDefaults();
        },

        get hasSingleScope() {
            return this.concerns.length === 1;
        },

        singleScopeLabel() {
            return this.concerns[0]?.summary ?? '';
        },

        resolveDefaultConcernId(payload = null) {
            if (this.defaultConcernId !== '') {
                return this.defaultConcernId;
            }

            const fromPayload = payload?.default_repair_order_concern_id;

            if (fromPayload !== null && fromPayload !== undefined && fromPayload !== '') {
                return concernIdString(fromPayload);
            }

            if (this.concerns.length === 1) {
                return concernIdString(this.concerns[0]?.id);
            }

            return '';
        },

        applyPreferredConcernId(preferredConcernId = undefined) {
            // Toolbar sends null when no sticky preference — treat like "no preference"
            // so preview / single-concern defaults can auto-scope.
            if (preferredConcernId === undefined || preferredConcernId === null || preferredConcernId === '') {
                this.defaultConcernId = '';

                return;
            }

            this.defaultConcernId = concernIdString(preferredConcernId);
        },

        applyInitialConcernDefault(concernId = null) {
            const resolved = concernIdString(concernId) || this.resolveDefaultConcernId();

            if (resolved !== '') {
                this.defaultConcernId = resolved;
            }
        },

        workGroupsForConcern(concernId) {
            const resolved = concernIdString(concernId);

            if (resolved === '') {
                return [];
            }

            const concern = this.concerns.find((entry) => entry.id === resolved);

            return (concern?.work_groups ?? []).filter((workGroup) => workGroup.has_labor_anchor);
        },

        defaultWorkGroupIdForConcern(concernId) {
            const anchored = this.workGroupsForConcern(concernId);

            if (anchored.length === 1) {
                return String(anchored[0].id);
            }

            return '';
        },

        matrixShortName(matrix) {
            const name = String(matrix?.name ?? matrix?.key ?? '').trim();

            if (name === '') {
                return '—';
            }

            return name.length > 18 ? name.slice(0, 16) + '…' : name;
        },

        shopDefaultMatrixKey() {
            if (filledMatrixKey(this.defaultPartsMatrixKey)) {
                return this.defaultPartsMatrixKey;
            }

            const marked = this.partsMatrices.find((matrix) => matrix.is_default);

            if (marked) {
                return marked.key;
            }

            const namedParts = this.partsMatrices.find((matrix) => {
                const name = matrix.name.toLowerCase();
                const key = matrix.key.toLowerCase();

                return key === 'aft-parts' || name === 'parts' || name === 'aft parts';
            });

            return namedParts?.key || this.partsMatrices[0]?.key || '';
        },

        matrixKeyForConcern(concernId) {
            const resolved = concernIdString(concernId);
            const concern = this.concerns.find((entry) => entry.id === resolved);
            const fromConcern = String(concern?.default_parts_matrix_key ?? '');

            return filledMatrixKey(fromConcern) ? fromConcern : this.shopDefaultMatrixKey();
        },

        resolvedMatrixKeyForRow(row) {
            return this.matrixKeyForConcern(row?.concern_id);
        },

        restoreImpliedMatrixKeys() {
            this.rows.forEach((row) => {
                if (row.pricing_matrix_explicit) {
                    return;
                }

                const key = this.resolvedMatrixKeyForRow(row);

                if (! filledMatrixKey(key)) {
                    return;
                }

                row.pricing_matrix_key = key;
                row.matrix_name = this.matrixNameForKey(key);
            });
        },

        matrixNameForKey(matrixKey) {
            const key = String(matrixKey ?? '');

            if (key === '') {
                return '—';
            }

            const matrix = this.partsMatrices.find((entry) => entry.key === key);

            return matrix?.name || key;
        },

        matrixNameForConcern(concernId) {
            return this.matrixNameForKey(this.matrixKeyForConcern(concernId));
        },

        applyScopeToRow(row, concernId, { keepExplicitMatrix = true } = {}) {
            const resolved = concernIdString(concernId);

            if (resolved !== '') {
                row.concern_id = resolved;
                row.work_group_id = this.defaultWorkGroupIdForConcern(resolved);
            }

            if (! keepExplicitMatrix || ! row.pricing_matrix_explicit) {
                row.pricing_matrix_key = this.matrixKeyForConcern(resolved);
                row.pricing_matrix_explicit = false;
                row.matrix_name = this.matrixNameForKey(row.pricing_matrix_key);
            }

            this.queueRowPreview(row, 0);
        },

        applyScopeToRows(concernId = null) {
            const resolved = concernIdString(concernId) || this.resolveDefaultConcernId();

            if (resolved === '') {
                return;
            }

            this.rows.forEach((row) => {
                this.applyScopeToRow(row, resolved);
            });
        },

        applyMatrixToRow(row, matrixKey, { explicit = true } = {}) {
            const key = String(matrixKey ?? '');

            if (key === '') {
                return;
            }

            row.pricing_matrix_key = key;
            row.pricing_matrix_explicit = Boolean(explicit);
            row.matrix_name = this.matrixNameForKey(key);
            this.queueRowPreview(row, 0);
        },

        onRowWorkGroupChange(row) {
            row.work_group_id = concernIdString(row.work_group_id);
        },

        onRowConcernChange(row) {
            this.syncingMatrices = true;
            this.applyScopeToRow(row, row.concern_id, { keepExplicitMatrix: true });

            this.$nextTick(() => {
                if (! row.pricing_matrix_explicit) {
                    const key = this.resolvedMatrixKeyForRow(row);

                    if (filledMatrixKey(key)) {
                        row.pricing_matrix_key = key;
                        row.matrix_name = this.matrixNameForKey(key);
                    }
                }

                this.syncingMatrices = false;
            });
        },

        onRowMatrixChange(row) {
            if (this.syncingMatrices || this.loading) {
                return;
            }

            row.pricing_matrix_explicit = filledMatrixKey(row.pricing_matrix_key);
            row.matrix_name = this.matrixNameForKey(row.pricing_matrix_key);
            this.queueRowPreview(row, 0);
        },

        applyQuoteDefaults(payload = null) {
            if (Array.isArray(payload?.parts_matrices) && payload.parts_matrices.length > 0) {
                this.partsMatrices = normalizeMatrices(payload.parts_matrices);
            }

            if (Array.isArray(payload?.concerns) && payload.concerns.length > 0) {
                this.concerns = normalizeConcerns(payload.concerns);
            }

            if (filledMatrixKey(payload?.default_parts_matrix_key)) {
                this.defaultPartsMatrixKey = String(payload.default_parts_matrix_key);
            }

            const concernId = this.resolveDefaultConcernId(payload);
            this.applyInitialConcernDefault(concernId);

            if (this.rows.length > 0) {
                this.applyScopeToRows(concernId);
                this.restoreImpliedMatrixKeys();
            }
        },

        destroy() {
            this.rows.forEach((row) => this.clearRowPreviewTimer(row));
        },

        selectedCount() {
            return this.rows.filter((row) => row.selected).length;
        },

        makeRow(line, defaultConcernId, defaultMatrixKey = '') {
            const concernId = concernIdString(defaultConcernId);
            const matrixKey = String(
                defaultMatrixKey || this.matrixKeyForConcern(concernId) || this.shopDefaultMatrixKey() || '',
            );

            return {
                ...line,
                selected: true,
                concern_id: concernId,
                work_group_id: this.defaultWorkGroupIdForConcern(concernId),
                part_cost: line.part_cost ?? '',
                sell: '',
                pricing_matrix_key: matrixKey,
                pricing_matrix_explicit: false,
                matrix_name: this.matrixNameForKey(matrixKey) || this.matrixNameForConcern(concernId),
                previewing: false,
                preview_timer: null,
                preview_sequence: 0,
                margin_percentage: null,
                guidance: '',
            };
        },

        dispatchPullState(loading, status = null) {
            const message = status ?? (loading ? this.loadingStatus : '');

            window.dispatchEvent(new CustomEvent('ark:partstech-pull-quote-state', {
                detail: { loading, status: message },
            }));
        },

        async loadQuote(forceCartSwitch = false, preferredConcernId = undefined) {
            this.applyPreferredConcernId(preferredConcernId);

            this.loading = true;
            this.loadingStatus = 'Pulling quote from PartsTech…';
            this.error = '';
            this.cartLocked = false;
            this.dispatchPullState(true);

            const panel = document.getElementById('partstech-quote-import');

            if (panel) {
                panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }

            const previewUrl = forceCartSwitch
                ? this.previewUrl + (this.previewUrl.includes('?') ? '&' : '?') + 'force_cart_switch=1'
                : this.previewUrl;

            try {
                const response = await fetch(previewUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const payload = await response.json();

                if (! response.ok) {
                    if (payload?.cart_locked) {
                        this.cartLocked = true;
                        this.error = payload?.message ?? 'PartsTech is busy on another repair order.';
                        window.dispatchEvent(new CustomEvent('ark:partstech-warning', {
                            detail: {
                                message: this.error,
                                cartLocked: true,
                            },
                        }));
                    } else {
                        this.error = payload?.message ?? 'Could not pull PartsTech quote.';
                    }
                    this.loaded = false;
                    this.rows = [];

                    return;
                }

                this.partsMatrices = normalizeMatrices(payload.parts_matrices ?? this.partsMatrices);
                this.concerns = normalizeConcerns(payload.concerns ?? this.concerns);

                const defaultConcernId = this.resolveDefaultConcernId(payload);
                this.applyQuoteDefaults(payload);

                const defaultMatrixKey = this.matrixKeyForConcern(defaultConcernId)
                    || this.shopDefaultMatrixKey();

                this.syncingMatrices = true;
                this.rows = (payload.lines ?? []).map((line) => this.makeRow(line, defaultConcernId, defaultMatrixKey));
                this.restoreImpliedMatrixKeys();

                if (this.rows.length === 0) {
                    const loginHint = payload?.partstech_login
                        ? ' Signed in for pull as ' + payload.partstech_login + '.'
                        : '';
                    this.error = 'PartsTech cart has no parts to import.' + loginHint;
                    this.loaded = false;

                    return;
                }

                this.applyScopeToRows(defaultConcernId);

                const pullWarnings = [];

                if (payload.po_sync_warning) {
                    pullWarnings.push('PO ' + this.poNumber + ' could not be synced: ' + payload.po_sync_warning);
                }

                if (Array.isArray(payload.warnings)) {
                    payload.warnings.forEach((warning) => {
                        if (warning) {
                            pullWarnings.push(warning);
                        }
                    });
                }

                if (pullWarnings.length > 0) {
                    window.dispatchEvent(new CustomEvent('ark:partstech-warning', {
                        detail: {
                            message: 'Quote pulled. ' + pullWarnings.join(' '),
                        },
                    }));
                }

                this.loaded = true;

                await this.$nextTick();
                this.restoreImpliedMatrixKeys();
                this.syncingMatrices = false;

                this.loadingStatus = 'Calculating sell prices…';
                this.dispatchPullState(true);
                await this.refreshAllRowPreviews();
            } catch {
                this.error = 'Could not reach ARK to pull the PartsTech quote.';
                this.loaded = false;
                this.rows = [];
            } finally {
                this.syncingMatrices = false;
                this.loading = false;
                this.dispatchPullState(false);
            }
        },

        onRowCostInput(row) {
            this.queueRowPreview(row, 120);
        },

        clearRowPreviewTimer(row) {
            if (row.preview_timer) {
                clearTimeout(row.preview_timer);
                row.preview_timer = null;
            }
        },

        queueRowPreview(row, delay = 200) {
            this.clearRowPreviewTimer(row);
            row.preview_timer = setTimeout(() => this.refreshRowPreview(row), delay);
        },

        async refreshAllRowPreviews() {
            for (const row of this.rows) {
                await this.refreshRowPreview(row);
            }
        },

        async refreshRowPreview(row) {
            if (! this.pricingPreviewUrl || String(row.part_cost).trim() === '' || ! row.concern_id) {
                row.guidance = row.concern_id ? 'Enter cost for matrix sell.' : 'Choose scope for matrix sell.';
                row.margin_percentage = null;
                row.sell = '';

                return;
            }

            const requestId = ++row.preview_sequence;
            row.previewing = true;

            try {
                const params = {
                    type: 'part',
                    part_cost: row.part_cost,
                    pricing_mode: 'matrix',
                    repair_order_concern_id: row.concern_id,
                };

                if (filledMatrixKey(row.pricing_matrix_key) && row.pricing_matrix_explicit) {
                    params.pricing_matrix_key = row.pricing_matrix_key;
                    params.pricing_matrix_explicit = '1';
                }

                const response = await fetch(this.pricingPreviewUrl, {
                    method: 'POST',
                    body: new URLSearchParams(params),
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                });

                if (! response.ok) {
                    row.guidance = 'Pricing unavailable.';

                    return;
                }

                const payload = await response.json();

                if (requestId !== row.preview_sequence) {
                    return;
                }

                if (! row.pricing_matrix_explicit && payload.pricing_matrix_key) {
                    row.pricing_matrix_key = String(payload.pricing_matrix_key);
                }

                row.matrix_name = payload.pricing_matrix_name || this.matrixNameForKey(row.pricing_matrix_key);
                row.guidance = payload.guidance || '';
                row.margin_percentage = payload.margin_percentage;

                if (payload.sell_from_matrix != null && payload.sell_from_matrix !== '') {
                    row.sell = payload.sell_from_matrix;
                } else if (payload.suggested_sell) {
                    row.sell = payload.suggested_sell.replace('$', '').replace(/,/g, '');
                } else {
                    row.sell = '';
                }
            } catch {
                row.guidance = 'Pricing unavailable.';
            } finally {
                if (requestId === row.preview_sequence) {
                    row.previewing = false;
                }
            }
        },

        async beforeSubmit(event) {
            const selected = this.rows.filter((row) => row.selected);

            if (selected.length === 0) {
                event.preventDefault();
                this.error = 'Select at least one part to import.';

                return;
            }

            const missingConcern = selected.find((row) => ! row.concern_id);

            if (missingConcern) {
                event.preventDefault();
                this.error = 'Assign a repair scope to every selected part.';

                return;
            }

            this.importing = true;
            this.error = '';

            for (const row of selected) {
                if (String(row.part_cost).trim() !== '') {
                    await this.refreshRowPreview(row);
                }
            }
        },
    };
}

function concernIdString(id) {
    if (id === null || id === undefined || id === '') {
        return '';
    }

    return String(id);
}

function filledMatrixKey(key) {
    return String(key ?? '').trim() !== '';
}

function normalizeMatrices(matrices) {
    return (matrices ?? []).map((matrix) => ({
        key: String(matrix.key ?? ''),
        name: String(matrix.name ?? matrix.key ?? ''),
        is_default: Boolean(matrix.is_default),
    })).filter((matrix) => matrix.key !== '');
}

function normalizeConcerns(concerns) {
    return concerns.map((concern) => ({
        ...concern,
        id: concernIdString(concern.id),
        default_parts_matrix_key: String(concern.default_parts_matrix_key ?? ''),
        default_parts_matrix_name: String(concern.default_parts_matrix_name ?? ''),
        work_groups: (concern.work_groups ?? []).map((workGroup) => ({
            id: concernIdString(workGroup.id),
            title: String(workGroup.title ?? ''),
            has_labor_anchor: Boolean(workGroup.has_labor_anchor),
        })),
    }));
}
