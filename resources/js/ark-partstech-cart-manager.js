export const IMPORT_STATUS = Object.freeze({ NEW: 'new', IMPORTED: 'imported', CHANGED: 'changed' });

const LAUNCH_CONTEXT_TTL_MS = 4 * 60 * 60 * 1000;
// A tab closed this soon after opening was usually blocked or severed, not used.
const RETURN_MIN_OPEN_MS = 3000;
const RETURN_WATCH_MS = 60 * 60 * 1000;

export function concernIdString(id) {
    if (id === null || id === undefined || id === '') {
        return '';
    }

    return String(id);
}

export function normalizeMatrices(matrices) {
    return (matrices ?? [])
        .map((matrix) => ({
            key: String(matrix.key ?? ''),
            name: String(matrix.name ?? matrix.key ?? ''),
            is_default: Boolean(matrix.is_default),
        }))
        .filter((matrix) => matrix.key !== '');
}

export function normalizeConcerns(concerns) {
    return (concerns ?? []).map((concern) => ({
        id: concernIdString(concern.id),
        summary: String(concern.summary ?? ''),
        default_parts_matrix_key: String(concern.default_parts_matrix_key ?? ''),
        work_groups: (concern.work_groups ?? [])
            .filter((workGroup) => workGroup.has_labor_anchor)
            .map((workGroup) => ({ id: concernIdString(workGroup.id), title: String(workGroup.title ?? '') })),
    }));
}

export function hasMatrix(matrices, key) {
    return matrices.some((matrix) => matrix.key === key);
}

export function shopDefaultMatrixKey(matrices, preferredKey = '') {
    if (preferredKey && hasMatrix(matrices, preferredKey)) {
        return preferredKey;
    }

    return matrices.find((matrix) => matrix.is_default)?.key || matrices[0]?.key || '';
}

/** The matrix a concern assigns: its billing posture's matrix, else the shop default. */
export function matrixKeyForConcern(context, concernId) {
    const concern = context.concerns.find((entry) => entry.id === concernIdString(concernId));
    const key = concern?.default_parts_matrix_key ?? '';

    return key && hasMatrix(context.matrices, key) ? key : shopDefaultMatrixKey(context.matrices, context.shopDefaultKey);
}

export function workGroupsForConcern(context, concernId) {
    return context.concerns.find((entry) => entry.id === concernIdString(concernId))?.work_groups ?? [];
}

/** Moves a row to a concern; the matrix follows the concern unless the advisor chose one for this row. */
export function assignConcern(row, concernId, context) {
    const resolved = concernIdString(concernId);
    const known = resolved === '' || context.concerns.some((entry) => entry.id === resolved);

    row.concern_id = known ? resolved : '';

    const workGroups = workGroupsForConcern(context, row.concern_id);
    row.work_group_id = workGroups.length === 1 ? workGroups[0].id : '';

    if (! row.matrix_explicit || ! hasMatrix(context.matrices, row.pricing_matrix_key)) {
        row.pricing_matrix_key = matrixKeyForConcern(context, row.concern_id);
        row.matrix_explicit = false;
    }

    return row;
}

export function chooseMatrix(row, key, context) {
    if (! hasMatrix(context.matrices, key)) {
        return row;
    }

    row.pricing_matrix_key = key;
    row.matrix_explicit = true;

    return row;
}

/**
 * One row per cart line. A re-pull keeps the advisor's concern, matrix and cost choices for the
 * same cart line; lines already on the estimate start unselected.
 */
export function makeRow(line, context, { previous = null, defaultConcernId = '' } = {}) {
    const status = line.import_status || IMPORT_STATUS.NEW;
    const cartCost = String(line.part_cost ?? '');
    const keepCost = previous?.cost_edited && previous.cart_part_cost === cartCost;

    const row = {
        source_key: String(line.source_key),
        description: String(line.description ?? ''),
        part_number: line.part_number ?? null,
        vendor_name: line.vendor_name ?? null,
        brand_name: line.brand_name ?? null,
        position_label: line.position_label ?? null,
        quantity: String(line.quantity ?? '1.00'),
        cart_part_cost: cartCost,
        part_cost: keepCost ? previous.part_cost : cartCost,
        cost_edited: Boolean(keepCost),
        import_status: status,
        imported_lines: Array.isArray(line.imported_lines) ? line.imported_lines : [],
        selected: status === IMPORT_STATUS.NEW ? (previous ? Boolean(previous.selected) : true) : false,
        concern_id: '',
        work_group_id: '',
        pricing_matrix_key: '',
        matrix_explicit: false,
        pricing: null,
        pricing_error: '',
        pricing_sequence: 0,
    };

    if (previous?.matrix_explicit && hasMatrix(context.matrices, previous.pricing_matrix_key)) {
        row.pricing_matrix_key = previous.pricing_matrix_key;
        row.matrix_explicit = true;
    }

    const firstImportedConcern = concernIdString(row.imported_lines[0]?.repair_order_concern_id);
    assignConcern(row, previous?.concern_id || (status === IMPORT_STATUS.NEW ? '' : firstImportedConcern) || defaultConcernId, context);

    if (previous?.work_group_id && workGroupsForConcern(context, row.concern_id).some((group) => group.id === previous.work_group_id)) {
        row.work_group_id = previous.work_group_id;
    }

    return row;
}

export function pricingRequestRow(row) {
    return {
        key: row.source_key,
        repair_order_concern_id: row.concern_id || null,
        part_cost: String(row.part_cost).trim(),
        quantity: row.quantity,
        pricing_matrix_key: row.pricing_matrix_key || null,
    };
}

export function canPrice(row) {
    return /^\d+(\.\d{0,2})?$/.test(String(row.part_cost).trim()) && row.pricing_matrix_key !== '';
}

export function assignmentsFor(rows) {
    return rows
        .filter((row) => row.selected && row.concern_id)
        .map((row) => ({
            source_key: row.source_key,
            repair_order_concern_id: row.concern_id,
            repair_order_work_group_id: row.work_group_id || null,
            part_cost: String(row.part_cost).trim(),
            pricing_matrix_key: row.pricing_matrix_key,
            import_again: row.import_status !== IMPORT_STATUS.NEW,
        }));
}

export function selectedSellCents(rows) {
    return rows
        .filter((row) => row.selected && row.pricing && ! row.pricing_error)
        .reduce((sum, row) => sum + Math.round(Number(row.quantity) * row.pricing.unit_sell_cents), 0);
}

export function formatCents(cents) {
    return '$' + (cents / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function formatQuantity(quantity) {
    const value = Number(quantity);

    return Number.isInteger(value) ? String(value) : String(value.toFixed(2)).replace(/0$/, '');
}

export function launchContextKey(repairOrderId) {
    return 'ark:partstech-launch:' + repairOrderId;
}

export function readLaunchContext(storage, repairOrderId, now = Date.now()) {
    try {
        const raw = JSON.parse(storage?.getItem(launchContextKey(repairOrderId)) || 'null');

        if (! raw || typeof raw.at !== 'number' || now - raw.at > LAUNCH_CONTEXT_TTL_MS) {
            return null;
        }

        return { concernId: concernIdString(raw.concernId), at: raw.at, pulled: Boolean(raw.pulled) };
    } catch {
        return null;
    }
}

export function writeLaunchContext(storage, repairOrderId, concernId, { pulled = false, now = Date.now() } = {}) {
    try {
        storage?.setItem(launchContextKey(repairOrderId), JSON.stringify({ concernId: concernIdString(concernId), at: now, pulled }));
    } catch {
        // Session storage is optional; the cart still opens without a remembered concern.
    }
}

export function partsCartStore() {
    return { count: 0, attention: false, open: false };
}

export function arkPartsTechCartManager(config = {}) {
    return {
        previewUrl: config.previewUrl,
        pricingUrl: config.pricingUrl,
        importUrl: config.importUrl,
        csrfToken: config.csrfToken || '',
        repairOrderId: String(config.repairOrderId ?? ''),
        cartReference: config.cartReference || '',
        estimateVersionField: config.estimateVersionField || 'opened_estimate_version',
        context: {
            matrices: normalizeMatrices(config.partsMatrices),
            concerns: normalizeConcerns(config.concerns),
            shopDefaultKey: String(config.defaultPartsMatrixKey ?? ''),
        },
        open: false,
        rows: [],
        loaded: false,
        loading: false,
        loadingStatus: '',
        importing: false,
        error: '',
        conflict: false,
        cartLocked: false,
        warnings: [],
        launchConcernId: '',
        bulkConcernId: '',
        returnWatch: null,
        pricingTimers: {},
        IMPORT_STATUS,
        formatQuantity,

        init() {
            const stored = readLaunchContext(window.sessionStorage, this.repairOrderId);

            if (stored) {
                this.launchConcernId = this.knownConcern(stored.concernId);
                this.store().attention = ! stored.pulled;
            }
        },

        destroy() {
            this.stopReturnWatch();
            Object.values(this.pricingTimers).forEach((timer) => clearTimeout(timer));
        },

        store() {
            return window.Alpine?.store('partsCart') ?? partsCartStore();
        },

        knownConcern(concernId) {
            const id = concernIdString(concernId);

            return this.context.concerns.some((concern) => concern.id === id) ? id : '';
        },

        get selectedRows() {
            return this.rows.filter((row) => row.selected);
        },

        get pendingCount() {
            return this.rows.filter((row) => row.import_status !== IMPORT_STATUS.IMPORTED).length;
        },

        get selectedSell() {
            return formatCents(selectedSellCents(this.rows));
        },

        get missingConcernCount() {
            return this.selectedRows.filter((row) => ! row.concern_id).length;
        },

        get canImport() {
            return ! this.importing
                && ! this.loading
                && this.selectedRows.length > 0
                && this.missingConcernCount === 0
                && this.selectedRows.every((row) => row.pricing && ! row.pricing_error);
        },

        workGroups(concernId) {
            return workGroupsForConcern(this.context, concernId);
        },

        concernSummary(concernId) {
            return this.context.concerns.find((concern) => concern.id === concernIdString(concernId))?.summary ?? '';
        },

        openManager(detail = {}) {
            if (detail.concernId !== undefined && detail.concernId !== null && detail.concernId !== '') {
                this.launchConcernId = this.knownConcern(detail.concernId);
            }

            this.open = true;
            this.store().open = true;
            this.store().attention = false;
            document.body.classList.add('modal-open');

            if (detail.pull || ! this.loaded) {
                this.pull();
            }

            this.$nextTick(() => this.$refs.pullButton?.focus());
        },

        close() {
            if (this.importing) {
                return;
            }

            this.open = false;
            this.store().open = false;
            document.body.classList.remove('modal-open');
        },

        openPartsTech() {
            window.dispatchEvent(new CustomEvent('ark:partstech-open', {
                detail: { concernId: this.launchConcernId || null },
            }));
        },

        onLaunched(detail = {}) {
            this.launchConcernId = this.knownConcern(detail.concernId);
            writeLaunchContext(window.sessionStorage, this.repairOrderId, this.launchConcernId);
            this.store().attention = true;
            this.watchForReturn(detail.catalogWindow ?? null);
        },

        watchForReturn(catalogWindow) {
            this.stopReturnWatch();

            if (! catalogWindow) {
                return;
            }

            const openedAt = Date.now();

            this.returnWatch = window.setInterval(() => {
                if (Date.now() - openedAt > RETURN_WATCH_MS) {
                    this.stopReturnWatch();

                    return;
                }

                if (! catalogWindow.closed) {
                    return;
                }

                this.stopReturnWatch();

                if (Date.now() - openedAt >= RETURN_MIN_OPEN_MS && ! this.open) {
                    this.openManager({ pull: true });
                }
            }, 1000);
        },

        stopReturnWatch() {
            if (this.returnWatch) {
                window.clearInterval(this.returnWatch);
                this.returnWatch = null;
            }
        },

        async pull(forceCartSwitch = false) {
            if (this.loading || this.importing) {
                return;
            }

            this.loading = true;
            this.loadingStatus = 'Pulling cart ' + this.cartReference + '…';
            this.error = '';
            this.conflict = false;
            this.cartLocked = false;
            this.warnings = [];

            const url = new URL(this.previewUrl, window.location.origin);

            if (forceCartSwitch) {
                url.searchParams.set('force_cart_switch', '1');
            }

            if (this.launchConcernId) {
                url.searchParams.set('concern_id', this.launchConcernId);
            }

            try {
                const response = await fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.cartLocked = Boolean(payload?.cart_locked);
                    this.error = payload?.message || 'Could not pull the PartsTech cart.';

                    return;
                }

                this.applyPayload(payload);

                if (readLaunchContext(window.sessionStorage, this.repairOrderId)) {
                    writeLaunchContext(window.sessionStorage, this.repairOrderId, this.launchConcernId, { pulled: true });
                }

                this.loadingStatus = 'Pricing parts…';
                await this.priceRows(this.rows);
            } catch {
                this.error = 'Could not reach ARK to pull the PartsTech cart.';
            } finally {
                this.loading = false;
                this.store().count = this.loaded ? this.pendingCount : 0;
            }
        },

        applyPayload(payload) {
            if (Array.isArray(payload.parts_matrices) && payload.parts_matrices.length > 0) {
                this.context.matrices = normalizeMatrices(payload.parts_matrices);
            }

            if (Array.isArray(payload.concerns)) {
                this.context.concerns = normalizeConcerns(payload.concerns);
            }

            if (payload.default_parts_matrix_key) {
                this.context.shopDefaultKey = String(payload.default_parts_matrix_key);
            }

            if (payload.cart_reference) {
                this.cartReference = payload.cart_reference;
            }

            this.launchConcernId = this.knownConcern(this.launchConcernId);

            const defaultConcernId = this.launchConcernId || this.knownConcern(payload.default_repair_order_concern_id);
            const previousByKey = Object.fromEntries(this.rows.map((row) => [row.source_key, row]));

            this.rows = (payload.lines ?? []).map((line) => makeRow(line, this.context, {
                previous: previousByKey[String(line.source_key)] ?? null,
                defaultConcernId,
            }));
            this.bulkConcernId = defaultConcernId;
            this.warnings = [payload.po_sync_warning, ...(payload.warnings ?? [])].filter(Boolean);
            this.loaded = true;
        },

        assignAll(concernId) {
            const resolved = this.knownConcern(concernId);

            if (resolved === '') {
                return;
            }

            const changed = this.rows.filter((row) => row.selected);

            changed.forEach((row) => assignConcern(row, resolved, this.context));
            this.priceRows(changed);
        },

        onConcernChange(row) {
            assignConcern(row, row.concern_id, this.context);
            this.priceRows([row]);
        },

        onMatrixChange(row, key) {
            chooseMatrix(row, key, this.context);
            this.priceRows([row]);
        },

        onCostInput(row) {
            row.cost_edited = String(row.part_cost).trim() !== row.cart_part_cost;
            clearTimeout(this.pricingTimers[row.source_key]);
            this.pricingTimers[row.source_key] = setTimeout(() => this.priceRows([row]), 250);
        },

        async priceRows(rows) {
            const priceable = rows.filter((row) => {
                if (canPrice(row)) {
                    return true;
                }

                row.pricing = null;
                row.pricing_error = String(row.part_cost).trim() === '' ? 'Enter a cost.' : 'Enter a valid cost.';

                return false;
            });

            if (priceable.length === 0 || ! this.pricingUrl) {
                return;
            }

            const sequences = new Map(priceable.map((row) => [row.source_key, ++row.pricing_sequence]));

            try {
                const response = await fetch(this.pricingUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ rows: priceable.map(pricingRequestRow) }),
                });
                const payload = await response.json().catch(() => ({}));
                const results = Object.fromEntries((payload.rows ?? []).map((result) => [result.key, result]));

                priceable.forEach((row) => {
                    if (sequences.get(row.source_key) !== row.pricing_sequence) {
                        return;
                    }

                    const result = response.ok ? results[row.source_key] : null;

                    row.pricing = result && ! result.error ? result : null;
                    row.pricing_error = result?.error || (result ? '' : (payload?.message || 'Pricing is unavailable.'));
                });
            } catch {
                priceable.forEach((row) => {
                    if (sequences.get(row.source_key) === row.pricing_sequence) {
                        row.pricing = null;
                        row.pricing_error = 'Pricing is unavailable.';
                    }
                });
            }
        },

        currentEstimateVersion() {
            return this.$refs.estimateVersion?.value ?? '';
        },

        async submit() {
            if (! this.canImport) {
                this.error = this.missingConcernCount > 0
                    ? 'Choose a concern for every selected part.'
                    : 'Select at least one priced part to import.';

                return;
            }

            this.importing = true;
            this.error = '';
            this.conflict = false;

            try {
                const response = await fetch(this.importUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        [this.estimateVersionField]: this.currentEstimateVersion(),
                        assignments: assignmentsFor(this.rows),
                    }),
                });
                const payload = await response.json().catch(() => ({}));

                if (response.ok && payload.redirect_url) {
                    this.store().count = 0;
                    navigateTo(payload.redirect_url);

                    return;
                }

                this.conflict = response.status === 409;
                this.error = payload?.message
                    || Object.values(payload?.errors ?? {})[0]?.[0]
                    || 'The parts could not be imported.';
                this.importing = false;
            } catch {
                this.error = 'Could not reach ARK to import the parts.';
                this.importing = false;
            }
        },
    };
}

function navigateTo(url) {
    const target = new URL(url, window.location.origin);

    if (target.pathname === window.location.pathname && target.search === window.location.search) {
        window.location.hash = target.hash;
        window.location.reload();

        return;
    }

    window.location.assign(target.toString());
}
