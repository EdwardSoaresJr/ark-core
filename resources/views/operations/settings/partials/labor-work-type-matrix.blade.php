@php
    $matrix = $laborWorkTypeMatrix ?? [
        'work_types' => [],
        'columns' => [],
        'cells' => [],
        'billing_minimums' => [],
        'labor_pricing_profile_version' => 0,
        'routes' => [],
    ];
    $workTypeKeys = collect($matrix['work_types'] ?? [])->pluck('key')->all();
    $requestedWorkType = request()->query('work_type');
    $initialWorkType = is_string($requestedWorkType) && in_array($requestedWorkType, $workTypeKeys, true)
        ? $requestedWorkType
        : ($matrix['work_types'][0]['key'] ?? 'mechanical');
@endphp

<div
    class="labor-matrix"
    data-settings-labor-matrix
    data-labor-matrix
    x-data="arkLaborMatrix(@js($matrix), @js($initialWorkType))"
    x-cloak
>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h3 class="mb-1">Labor rate matrix</h3>
            <p class="text-secondary small mb-0">
                Select a work type, then set hourly rates, billing minimums, and rounding for each billing class.
                Mechanical is the Default Work Type. Changes apply to new repair orders only. Use $0.00 for no charge.
            </p>
        </div>
        <div class="small" aria-live="polite">
            <span class="text-secondary" x-show="status === 'saving'">Saving…</span>
            <span class="text-success" x-show="status === 'saved'">All changes saved</span>
            <span class="text-danger" x-show="status === 'error'">
                Could not save —
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline" @click="retryLast()">Retry</button>
            </span>
        </div>
    </div>

    <div class="ops-financial-master-detail">
        <div class="ops-financial-master-detail__nav">
            <div class="list-group list-group-flush border rounded" data-labor-work-type-nav>
                <template x-for="workType in matrix.work_types" :key="workType.key">
                    <button
                        type="button"
                        class="list-group-item list-group-item-action ops-financial-master-detail__nav-item"
                        :class="{ active: selectedWorkType === workType.key }"
                        @click="selectWorkType(workType.key)"
                        :data-labor-work-type="workType.key"
                    >
                        <span class="ops-financial-master-detail__nav-label" x-text="workType.label"></span>
                        <span class="badge bg-azure-lt text-azure" x-show="workType.is_default">Default</span>
                    </button>
                </template>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2" @click="promptAddWorkType()">
                + Add Work Type
            </button>
        </div>

        <div class="ops-financial-master-detail__editor">
            <div class="mb-2">
                <h4 class="mb-0" data-labor-selected-heading>
                    Rates for <span x-text="selectedWorkTypeLabel()"></span>
                </h4>
                <p class="form-hint mb-0">
                    Hourly Rate and Round To are this work type × billing class. Min Hrs is shared for the billing class across all work types. Class Settings opens commercial configuration.
                </p>
            </div>
            <div class="table-responsive border rounded">
                <table class="table table-vcenter table-sm mb-0 labor-matrix-table labor-matrix-table--hybrid" data-labor-rate-editor>
                    <thead>
                        <tr>
                            <th class="labor-matrix-th">Billing Class</th>
                            <th class="labor-matrix-th" style="min-width: 7.5rem">Hourly Rate</th>
                            <th class="labor-matrix-th" style="min-width: 11rem">
                                Min Hrs
                                <div class="form-hint mb-0 fw-normal">Shared · all work types</div>
                            </th>
                            <th class="labor-matrix-th" style="min-width: 9.5rem">
                                Round To
                                <div class="form-hint mb-0 fw-normal">This work type × class</div>
                            </th>
                            <th class="labor-matrix-th text-end" style="width: 5.5rem">Class</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="column in matrix.columns" :key="column.value">
                            <tr :data-billing-class="column.value">
                                <td class="py-1">
                                    <div class="fw-medium" x-text="column.label"></div>
                                </td>
                                <td class="py-1">
                                    <div class="input-group input-group-sm" style="max-width: 7.5rem">
                                        <span class="input-group-text">$</span>
                                        <input
                                            type="text"
                                            class="form-control font-monospace text-end"
                                            :value="rateInput(selectedWorkType, column.value)"
                                            @change="setRateInput(selectedWorkType, column.value, $event.target.value); saveRate(selectedWorkType, column.value)"
                                            @keydown.enter.prevent="setRateInput(selectedWorkType, column.value, $event.target.value); saveRate(selectedWorkType, column.value)"
                                            inputmode="decimal"
                                            :aria-label="`${selectedWorkTypeLabel()} ${column.label} hourly rate`"
                                            data-labor-hourly-rate
                                        >
                                    </div>
                                </td>
                                <td class="py-1">
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <select
                                            class="form-select form-select-sm"
                                            style="max-width: 6.75rem"
                                            :value="minimumRow(column.value).mode"
                                            @change="setMinimumMode(column.value, $event.target.value)"
                                            :aria-label="`${column.label} minimum mode`"
                                            data-labor-min-mode
                                        >
                                            <option value="none">None</option>
                                            <option value="per_line">Per line</option>
                                            <option value="per_concern">Per concern</option>
                                            <option value="per_ro">Per RO</option>
                                        </select>
                                        <input
                                            type="text"
                                            class="form-control form-control-sm font-monospace"
                                            style="max-width: 4.25rem"
                                            :value="minimumRow(column.value).hours"
                                            :disabled="minimumRow(column.value).mode === 'none'"
                                            @change="setMinimumHours(column.value, $event.target.value)"
                                            @keydown.enter.prevent="setMinimumHours(column.value, $event.target.value)"
                                            inputmode="decimal"
                                            :aria-label="`${column.label} minimum hours`"
                                            data-labor-min-hours
                                        >
                                    </div>
                                </td>
                                <td class="py-1">
                                    <select
                                        class="form-select form-select-sm"
                                        :value="cell(selectedWorkType, column.value).rounding_rule || 'quarter'"
                                        @change="saveCellRounding(column.value, $event.target.value)"
                                        :aria-label="`${selectedWorkTypeLabel()} ${column.label} rounding`"
                                        data-labor-rounding
                                    >
                                        <template x-for="option in (matrix.rounding_options || [])" :key="option.value">
                                            <option :value="option.value" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="py-1 text-end">
                                    <template x-if="column.has_customer_class">
                                        <button
                                            type="button"
                                            class="btn btn-ghost-secondary btn-icon btn-sm"
                                            @click="openClassSettings(column)"
                                            data-labor-class-settings
                                            title="Class Settings"
                                            aria-label="Class Settings"
                                        >
                                            <x-tabler.icon name="settings" class="icon icon-sm" />
                                        </button>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="button" class="btn btn-outline-primary btn-sm" @click="promptAddLaborColumn()" data-labor-add-column>
                    + Add Labor Column
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="openManageBillingClasses()" data-labor-manage-billing-classes>
                    Manage Billing Classes
                </button>
            </div>
            <p class="form-hint mb-0 mt-1">
                Manual line overrides still follow each work type’s stored permission. Manage Billing Classes covers shared commercial settings.
            </p>
        </div>
    </div>

    <div
        class="modal modal-blur"
        tabindex="-1"
        data-labor-add-dialog
        x-cloak
        :class="{ show: !!addDialog }"
        :style="addDialog
            ? 'display: block !important; background: rgba(22, 28, 45, 0.35);'
            : 'display: none !important'"
    >
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" x-text="addDialog?.title"></h5>
                    <button type="button" class="btn-close" @click="addDialog = null"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" x-text="addDialog?.label"></label>
                    <input type="text" class="form-control" x-model="addDialogName" @keydown.enter.prevent="confirmAddDialog()" maxlength="64">
                    <p class="form-hint mb-0 mt-2" x-show="addDialog?.hint" x-text="addDialog?.hint"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" @click="addDialog = null">Cancel</button>
                    <button type="button" class="btn btn-primary" @click="confirmAddDialog()">Add</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('arkLaborMatrix', (initial, initialWorkType = null) => ({
        matrix: initial,
        selectedWorkType: initialWorkType || initial.work_types?.[0]?.key || 'mechanical',
        status: 'idle',
        lastRequest: null,
        addDialog: null,
        addDialogName: '',
        init() {
            if (!this.matrix.work_types.some((row) => row.key === this.selectedWorkType)) {
                this.selectedWorkType = this.matrix.work_types[0]?.key || 'mechanical';
            }
            this.syncWorkTypeUrl(false);
            this.$watch('status', (value) => {
                if (value === 'saved') {
                    setTimeout(() => { if (this.status === 'saved') this.status = 'idle'; }, 1800);
                }
            });
            window.addEventListener('popstate', () => {
                const fromUrl = new URLSearchParams(window.location.search).get('work_type');
                if (fromUrl && this.matrix.work_types.some((row) => row.key === fromUrl)) {
                    this.selectedWorkType = fromUrl;
                }
            });
        },
        selectWorkType(key) {
            if (!this.matrix.work_types.some((row) => row.key === key)) {
                return;
            }
            this.selectedWorkType = key;
            this.syncWorkTypeUrl(true);
        },
        shopSettings() {
            const el = this.$el.closest('[data-shop-settings]');
            return el ? Alpine.$data(el) : null;
        },
        syncWorkTypeUrl(push) {
            // Labor stays mounted while other Financial tabs are shown; never steal the active tab.
            // Alpine $root is this component, not Shop Settings — read the shop host explicitly.
            if (this.shopSettings()?.financialTab !== 'labor') {
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.set('section', 'financial');
            url.searchParams.set('financial-tab', 'labor');
            url.searchParams.set('work_type', this.selectedWorkType);
            if (push) {
                window.history.pushState({}, '', url);
            } else {
                window.history.replaceState({}, '', url);
            }
        },
        selectedWorkTypeLabel() {
            return this.matrix.work_types.find((row) => row.key === this.selectedWorkType)?.label || 'Work type';
        },
        cell(workType, billingClass) {
            this.matrix.cells[workType] ||= {};
            this.matrix.cells[workType][billingClass] ||= {
                settings_mode: 'custom',
                effective_rate_cents: null,
                rate_display: '—',
                rounding_rule: 'quarter',
            };
            return this.matrix.cells[workType][billingClass];
        },
        minimumRow(billingClass) {
            this.matrix.billing_minimums ||= {};
            this.matrix.billing_minimums[billingClass] ||= {
                mode: 'none',
                hours: '0.00',
                shortfall_rate_source: 'standard_mechanical',
                shortfall_rate_cents: this.matrix.standard_rate_cents,
            };
            return this.matrix.billing_minimums[billingClass];
        },
        rateInput(workType, billingClass) {
            const cents = this.cell(workType, billingClass).effective_rate_cents;
            return cents == null ? '' : (cents / 100).toFixed(2);
        },
        setRateInput(workType, billingClass, value) {
            const cents = Math.round(parseFloat(value || '0') * 100);
            this.cell(workType, billingClass).effective_rate_cents = Number.isFinite(cents) ? cents : 0;
            this.cell(workType, billingClass).rate_display = '$' + ((this.cell(workType, billingClass).effective_rate_cents || 0) / 100).toFixed(2);
        },
        async setMinimumMode(billingClass, mode) {
            const row = this.minimumRow(billingClass);
            row.mode = mode;
            if (mode === 'none') {
                row.hours = '0.00';
            } else if (!parseFloat(row.hours || '0')) {
                row.hours = '1.00';
            }
            await this.saveMinimum(billingClass);
        },
        async setMinimumHours(billingClass, hours) {
            const row = this.minimumRow(billingClass);
            const parsed = parseFloat(hours || '0');
            row.hours = Number.isFinite(parsed) ? parsed.toFixed(2) : '0.00';
            if (row.mode === 'none' && parsed > 0) {
                row.mode = 'per_line';
            }
            await this.saveMinimum(billingClass);
        },
        async saveMinimum(billingClass) {
            const row = this.minimumRow(billingClass);
            await this.request(this.matrix.routes.minimum, 'PATCH', {
                billing_class: billingClass,
                mode: row.mode || 'none',
                hours: row.hours || '0.00',
                shortfall_rate_source: row.shortfall_rate_source || 'standard_mechanical',
                shortfall_rate: row.shortfall_rate_cents != null
                    ? (row.shortfall_rate_cents / 100).toFixed(2)
                    : '',
            });
        },
        async saveCellRounding(billingClass, roundingRule) {
            this.cell(this.selectedWorkType, billingClass).rounding_rule = roundingRule;
            await this.request(this.matrix.routes.rounding || this.matrix.routes.work_type_rounding, 'PATCH', {
                work_type: this.selectedWorkType,
                billing_class: billingClass,
                rounding_rule: roundingRule,
            });
        },
        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },
        async request(url, method, body) {
            this.status = 'saving';
            this.lastRequest = { url, method, body };
            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        ...body,
                        expected_version: this.matrix.labor_pricing_profile_version,
                    }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.status = 'error';
                    throw new Error(payload.message || Object.values(payload.errors || {})[0]?.[0] || 'Save failed');
                }
                if (payload.matrix) {
                    const selected = this.selectedWorkType;
                    this.matrix = payload.matrix;
                    if (!this.matrix.work_types.some((row) => row.key === selected)) {
                        this.selectedWorkType = this.matrix.work_types[0]?.key || selected;
                    } else {
                        this.selectedWorkType = selected;
                    }
                }
                this.status = 'saved';
                return payload;
            } catch (error) {
                this.status = 'error';
                throw error;
            }
        },
        async saveRate(workType, billingClass) {
            const cell = this.cell(workType, billingClass);
            const cents = Number(cell.effective_rate_cents || 0);
            const mode = cents <= 0 ? 'zero' : 'custom';
            cell.settings_mode = mode;
            await this.request(this.matrix.routes.rate, 'PATCH', {
                work_type: workType,
                billing_class: billingClass,
                mode,
                rate: (Math.max(0, cents) / 100).toFixed(2),
            });
        },
        promptAddWorkType() {
            this.addDialogName = '';
            this.addDialog = {
                kind: 'work_type',
                title: 'Add work type',
                label: 'Work type name',
                hint: 'Creates a selectable work type with rates for every billing class. Applies to new repair orders only.',
            };
        },
        promptAddLaborColumn() {
            this.addDialogName = '';
            this.addDialog = {
                kind: 'billing_class',
                title: 'Add labor column',
                label: 'Labor column name',
                hint: 'Creates a labor-pricing column with its own rates. Does not create a customer Billing Class — use Manage Billing Classes for that.',
            };
        },
        promptAddBillingClass() {
            this.promptAddLaborColumn();
        },
        openClassSettings(column) {
            this.$dispatch('labor-open-class-settings', { key: column.customer_class_key || null, label: column.label });
        },
        openManageBillingClasses() {
            this.$dispatch('labor-open-manage-billing-classes');
        },
        async confirmAddDialog() {
            const name = (this.addDialogName || '').trim();
            if (!name || !this.addDialog) return;
            const kind = this.addDialog.kind;
            this.addDialog = null;
            if (kind === 'work_type') {
                const payload = await this.request(this.matrix.routes.work_type, 'POST', { name });
                if (payload.work_type?.key) {
                    this.selectWorkType(payload.work_type.key);
                }
                return;
            }
            await this.request(this.matrix.routes.billing_class, 'POST', {
                name,
                base_posture: 'customer_pay',
            });
        },
        async retryLast() {
            if (!this.lastRequest) return;
            await this.request(this.lastRequest.url, this.lastRequest.method, this.lastRequest.body);
        },
    }));
});
</script>
