export function arkDealerQuoteCapture(config) {
    return {
        analyzeUrl: config.analyzeUrl,
        importUrl: config.importUrl,
        pricingPreviewUrl: config.pricingPreviewUrl,
        csrfToken: config.csrfToken || '',
        concerns: normalizeConcerns(config.initialConcerns ?? []),
        partsMatrices: normalizeMatrices(config.initialPartsMatrices ?? []),
        defaultConcernId: concernIdString(config.defaultConcernId),
        open: false,
        step: 'upload',
        analyzing: false,
        importing: false,
        error: '',
        quoteText: '',
        quoteFileName: '',
        quoteFile: null,
        capture: null,
        rows: [],
        bulk: {
            concern_id: '',
            work_group_id: '',
            pricing_matrix_key: '',
        },

        init() {
            window.addEventListener('ark:dealer-quote-capture-open', () => this.openCapture());
        },

        openCapture() {
            this.open = true;
            this.step = 'upload';
            this.error = '';
            this.analyzing = false;
            this.importing = false;
        },

        closeCapture() {
            if (this.analyzing || this.importing) {
                return;
            }

            this.open = false;
            this.resetUpload();
        },

        resetUpload() {
            this.step = 'upload';
            this.error = '';
            this.quoteText = '';
            this.quoteFileName = '';
            this.quoteFile = null;
            this.capture = null;
            this.rows = [];
            this.bulk = {
                concern_id: '',
                work_group_id: '',
                pricing_matrix_key: '',
            };
        },

        onFileChange(event) {
            const file = event.target.files?.[0] ?? null;
            this.quoteFile = file;
            this.quoteFileName = file?.name ?? '';
        },

        get hasSingleScope() {
            return this.concerns.length === 1;
        },

        get allSelected() {
            return this.rows.length > 0 && this.rows.every((row) => row.selected);
        },

        set allSelected(value) {
            this.rows.forEach((row) => {
                row.selected = Boolean(value);
            });
        },

        selectedCount() {
            return this.rows.filter((row) => row.selected).length;
        },

        selectedDealerTotal() {
            return this.rows
                .filter((row) => row.selected)
                .reduce((sum, row) => {
                    const qty = Number(row.quantity) || 0;
                    const cost = Number(row.part_cost) || 0;

                    return sum + (qty * cost);
                }, 0)
                .toFixed(2);
        },

        matrixShortName(matrix) {
            const name = String(matrix?.name ?? matrix?.key ?? '').trim();

            if (name === '') {
                return '—';
            }

            return name
                .replace(/\s+parts\b/gi, '')
                .replace(/\s+no\s+markup\b/gi, '')
                .replace(/\s+/g, ' ')
                .trim() || name;
        },

        uploadPreviewLines() {
            return previewPartLinesFromPaste(this.quoteText);
        },

        resolveDefaultConcernId(payload = null) {
            const fromPayload = payload?.default_repair_order_concern_id;

            if (fromPayload !== null && fromPayload !== undefined && fromPayload !== '') {
                return concernIdString(fromPayload);
            }

            if (this.defaultConcernId !== '') {
                return this.defaultConcernId;
            }

            if (this.concerns.length === 1) {
                return concernIdString(this.concerns[0]?.id);
            }

            return '';
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

        matrixKeyForConcern(concernId) {
            const resolved = concernIdString(concernId);
            const concern = this.concerns.find((entry) => entry.id === resolved);

            return String(concern?.default_parts_matrix_key ?? '');
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

        onBulkConcernChange() {
            this.bulk.work_group_id = this.defaultWorkGroupIdForConcern(this.bulk.concern_id);

            if (this.bulk.pricing_matrix_key === '') {
                this.bulk.pricing_matrix_key = this.matrixKeyForConcern(this.bulk.concern_id);
            }
        },

        applyScopeToRow(row, concernId, { keepExplicitMatrix = true } = {}) {
            const resolved = concernIdString(concernId);

            if (resolved === '') {
                return;
            }

            row.concern_id = resolved;
            row.work_group_id = this.defaultWorkGroupIdForConcern(resolved);

            if (! keepExplicitMatrix || ! row.pricing_matrix_explicit) {
                row.pricing_matrix_key = this.matrixKeyForConcern(resolved);
                row.pricing_matrix_explicit = false;
                row.matrix_name = this.matrixNameForKey(row.pricing_matrix_key);
            }

            this.queueRowPreview(row, 0);
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

        applyWorkGroupToRow(row, workGroupId) {
            const resolved = concernIdString(workGroupId);
            const allowed = this.workGroupsForConcern(row.concern_id).some((entry) => entry.id === resolved);

            if (! allowed) {
                return;
            }

            row.work_group_id = resolved;
        },

        applyToSelected() {
            const selected = this.rows.filter((row) => row.selected);

            if (selected.length === 0) {
                this.error = 'Select at least one part before applying.';

                return;
            }

            const concernId = concernIdString(this.bulk.concern_id);
            const workGroupId = concernIdString(this.bulk.work_group_id);
            const matrixKey = String(this.bulk.pricing_matrix_key ?? '');

            if (concernId === '' && workGroupId === '' && matrixKey === '') {
                this.error = 'Choose a scope, repair action, or matrix to apply.';

                return;
            }

            this.error = '';

            selected.forEach((row) => {
                if (concernId !== '') {
                    this.applyScopeToRow(row, concernId, { keepExplicitMatrix: matrixKey === '' });
                }

                if (workGroupId !== '') {
                    this.applyWorkGroupToRow(row, workGroupId);
                }

                if (matrixKey !== '') {
                    this.applyMatrixToRow(row, matrixKey, { explicit: true });
                }
            });
        },

        makeRow(line, defaultConcernId, defaultMatrixKey = '') {
            const concernId = concernIdString(defaultConcernId);
            const matrixKey = String(defaultMatrixKey || this.matrixKeyForConcern(concernId) || '');

            return {
                ...line,
                selected: true,
                concern_id: concernId,
                work_group_id: this.defaultWorkGroupIdForConcern(concernId),
                description: String(line.description ?? ''),
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

        onRowConcernChange(row) {
            this.applyScopeToRow(row, row.concern_id, { keepExplicitMatrix: true });
        },

        onRowMatrixChange(row) {
            row.pricing_matrix_explicit = filledMatrixKey(row.pricing_matrix_key);
            row.matrix_name = this.matrixNameForKey(row.pricing_matrix_key);
            this.queueRowPreview(row, 0);
        },

        onRowWorkGroupChange(row) {
            row.work_group_id = concernIdString(row.work_group_id);
        },

        async analyzeQuote() {
            this.analyzing = true;
            this.error = '';

            const body = new FormData();
            body.append('quote_text', this.quoteText || '');

            if (this.quoteFile) {
                body.append('quote_pdf', this.quoteFile);
            }

            try {
                const response = await fetch(this.analyzeUrl, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                });

                const payload = await response.json();

                if (! response.ok) {
                    this.error = payload?.message ?? 'Could not analyze dealer quote.';

                    return;
                }

                this.concerns = normalizeConcerns(payload.concerns ?? this.concerns);
                this.partsMatrices = normalizeMatrices(payload.parts_matrices ?? this.partsMatrices);

                const defaultConcernId = this.resolveDefaultConcernId(payload);
                this.defaultConcernId = defaultConcernId || this.defaultConcernId;

                const defaultMatrixKey = String(
                    payload.default_parts_matrix_key
                    || this.matrixKeyForConcern(defaultConcernId)
                    || '',
                );

                this.bulk = {
                    concern_id: defaultConcernId,
                    work_group_id: this.defaultWorkGroupIdForConcern(defaultConcernId),
                    pricing_matrix_key: defaultMatrixKey,
                };

                this.capture = {
                    supplier_name: payload.supplier_name,
                    quote_number: payload.quote_number,
                    vehicle_description: payload.vehicle_description,
                    vin: payload.vin,
                    dealer_total_cents: payload.dealer_total_cents,
                    dealer_total: payload.dealer_total,
                    raw_text: payload.raw_text,
                    original_filename: payload.original_filename,
                    temp_storage_path: payload.temp_storage_path,
                    lines: payload.lines ?? [],
                };

                this.rows = (payload.lines ?? []).map((line) => this.makeRow(line, defaultConcernId, defaultMatrixKey));

                if (this.rows.length === 0) {
                    this.error = 'No part lines detected in this quote.';

                    return;
                }

                this.step = 'review';
                await this.$nextTick();
                await this.refreshAllRowPreviews();
            } catch {
                this.error = 'Could not reach ARK to analyze the dealer quote.';
            } finally {
                this.analyzing = false;
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

            try {
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
                this.error = 'Select at least one part to add.';

                return;
            }

            const missingConcern = selected.find((row) => ! row.concern_id);

            if (missingConcern) {
                event.preventDefault();
                this.error = 'Assign a repair scope to every selected part.';

                return;
            }

            const missingName = selected.find((row) => String(row.description ?? '').trim() === '');

            if (missingName) {
                event.preventDefault();
                this.error = 'Give every selected part a name.';

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

function filledMatrixKey(key) {
    return String(key ?? '').trim() !== '';
}

function previewPartLinesFromPaste(quoteText) {
    const text = String(quoteText ?? '').trim();

    if (text === '') {
        return [];
    }

    const rows = [];
    const withPart = /^(\d+(?:\.\d+)?)\s+([A-Z0-9][A-Z0-9.\-\/]{2,})\s+(.+?)\s+(\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2})(?:\s+\d{1,3}(?:,\d{3})*(?:\.\d{2})|\s+\d+\.\d{2})?\s*$/i;
    const withoutPart = /^(\d+(?:\.\d+)?)\s+([A-Za-z].+?)\s+(\d{1,3}(?:,\d{3})*(?:\.\d{2})|\d+\.\d{2})\s*$/;

    for (const rawLine of text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n')) {
        const line = rawLine.trim().replace(/[—–−]/g, '-').replace(/[ \t]+/g, ' ');

        if (line === '') {
            continue;
        }

        let match = line.match(withPart);

        if (match) {
            rows.push({
                quantity: match[1],
                part_number: match[2].toUpperCase(),
                description: match[3].trim(),
                part_cost: match[4].replace(/,/g, ''),
            });

            continue;
        }

        match = line.match(withoutPart);

        if (match && match[2].trim().length >= 3) {
            rows.push({
                quantity: match[1],
                part_number: '',
                description: match[2].trim(),
                part_cost: match[3].replace(/,/g, ''),
            });
        }
    }

    return rows;
}

function concernIdString(id) {
    if (id === null || id === undefined || id === '') {
        return '';
    }

    return String(id);
}

function normalizeMatrices(matrices) {
    return (matrices ?? []).map((matrix) => ({
        key: String(matrix.key ?? ''),
        name: String(matrix.name ?? matrix.key ?? ''),
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
