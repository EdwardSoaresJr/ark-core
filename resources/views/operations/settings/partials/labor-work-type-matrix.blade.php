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
                Select a work type, edit hourly rates inline, and open Rules for billing-class minimums.
                Mechanical is the Default Work Type. Changes apply to new repair orders only.
            </p>
        </div>
        <div class="small" aria-live="polite">
            <span class="text-secondary" x-show="status === 'idle'">Ready</span>
            <span class="text-secondary" x-show="status === 'saving'">Saving…</span>
            <span class="text-success" x-show="status === 'saved'">Saved</span>
            <span class="text-danger" x-show="status === 'error'">
                Not saved — <button type="button" class="btn btn-link btn-sm p-0 align-baseline" @click="retryLast()">Retry</button>
            </span>
        </div>
    </div>

    <div class="border rounded px-3 py-2 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2" data-labor-default-rate>
        <div>
            <div class="fw-semibold">Default Labor Rate</div>
            <div class="form-hint mb-0">
                Shop-wide inheritance source.
                <span x-text="inheritCount()"></span> matrix entries use this rate.
                Updating it changes new repair orders only.
            </div>
        </div>
        <div class="input-group input-group-sm" style="max-width: 10rem">
            <span class="input-group-text">$</span>
            <input
                type="text"
                class="form-control font-monospace text-end"
                :value="defaultRateInput()"
                @change="saveDefaultLaborRate($event.target.value)"
                @keydown.enter.prevent="saveDefaultLaborRate($event.target.value)"
                inputmode="decimal"
                aria-label="Default Labor Rate"
                data-labor-default-rate-input
            >
            <span class="input-group-text">/hr</span>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-3">
            <div class="list-group list-group-flush border rounded" data-labor-work-type-nav>
                <template x-for="workType in matrix.work_types" :key="workType.key">
                    <button
                        type="button"
                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                        :class="{ active: selectedWorkType === workType.key }"
                        @click="selectWorkType(workType.key)"
                        :data-labor-work-type="workType.key"
                    >
                        <span x-text="workType.label"></span>
                        <span class="badge bg-azure-lt text-azure" x-show="workType.is_default">Default Work Type</span>
                    </button>
                </template>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2" @click="promptAddWorkType()">
                + Add Work Type
            </button>
        </div>

        <div class="col-12 col-lg-9">
            <div class="mb-2">
                <h4 class="mb-0" data-labor-selected-heading>
                    Billing Classes for <span x-text="selectedWorkTypeLabel()"></span>
                </h4>
                <p class="form-hint mb-0">
                    Configure hourly rates for <span x-text="selectedWorkTypeLabel()"></span> work.
                </p>
            </div>
            <div class="table-responsive border rounded">
                <table class="table table-vcenter table-sm mb-0" data-labor-rate-editor>
                    <thead>
                        <tr>
                            <th class="bg-light">Labor column</th>
                            <th class="bg-light text-end" style="width: 14rem">Hourly rate</th>
                            <th class="bg-light text-end" style="width: 11rem">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="column in matrix.columns" :key="column.value">
                            <tr :data-billing-class="column.value">
                                <td>
                                    <div class="fw-medium" x-text="column.label"></div>
                                    <div class="form-hint mb-0" x-show="cell(selectedWorkType, column.value).is_default_cell">
                                        Default Work Type × Retail — inherits Default Labor Rate
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column align-items-end gap-1">
                                        <select
                                            class="form-select form-select-sm"
                                            style="max-width: 13rem"
                                            x-model="cell(selectedWorkType, column.value).settings_mode"
                                            @change="saveRate(selectedWorkType, column.value)"
                                            :aria-label="`${selectedWorkTypeLabel()} ${column.label} rate mode`"
                                        >
                                            <option value="standard">Use Default Labor Rate</option>
                                            <option value="custom">Custom Rate</option>
                                            <option value="zero">No Charge</option>
                                        </select>
                                        <div class="input-group input-group-sm" style="max-width: 9.5rem" x-show="cell(selectedWorkType, column.value).settings_mode === 'custom'">
                                            <span class="input-group-text">$</span>
                                            <input
                                                type="text"
                                                class="form-control font-monospace text-end"
                                                :value="rateInput(selectedWorkType, column.value)"
                                                @change="setRateInput(selectedWorkType, column.value, $event.target.value); saveRate(selectedWorkType, column.value)"
                                                @keydown.enter.prevent="setRateInput(selectedWorkType, column.value, $event.target.value); saveRate(selectedWorkType, column.value)"
                                                inputmode="decimal"
                                                :aria-label="`${selectedWorkTypeLabel()} ${column.label} hourly rate`"
                                            >
                                        </div>
                                        <div
                                            class="form-hint mb-0 font-monospace"
                                            x-show="cell(selectedWorkType, column.value).settings_mode === 'standard'"
                                            x-text="`Uses Default Labor Rate · ${cell(selectedWorkType, column.value).rate_display}/hr`"
                                        ></div>
                                        <div
                                            class="form-hint mb-0 font-monospace"
                                            x-show="cell(selectedWorkType, column.value).settings_mode === 'zero'"
                                            x-text="`${cell(selectedWorkType, column.value).rate_display} · No Charge`"
                                        ></div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            @click="openRules(column)"
                                        >
                                            Rules
                                        </button>
                                        <template x-if="column.has_customer_class">
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                @click="openClassSettings(column)"
                                                data-labor-class-settings
                                            >
                                                Class Settings
                                            </button>
                                        </template>
                                    </div>
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
                Labor columns set rates and Rules for this work type. Billing Classes hold shared commercial settings (fees, discounts, parts, documents).
            </p>
        </div>
    </div>

    <div
        class="modal modal-blur"
        tabindex="-1"
        x-ref="rulesModal"
        data-labor-rules-modal
        x-cloak
        :class="{ show: !!rulesColumn }"
        :style="rulesColumn
            ? 'display: block !important; background: rgba(22, 28, 45, 0.35);'
            : 'display: none !important'"
    >
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span x-text="rulesColumn?.label"></span> rules
                    </h5>
                    <button type="button" class="btn-close" @click="closeRules()"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">
                        Minimums belong to this billing class for every work type.
                        Only eligible labor on concerns with this billing class counts toward the minimum.
                    </p>
                    <label class="form-label">Minimum labor</label>
                    <select class="form-select mb-2" x-model="rulesDraft.mode">
                        <option value="none">No minimum</option>
                        <option value="per_line">Minimum per labor line</option>
                        <option value="per_ro">Minimum per repair order</option>
                    </select>
                    <div x-show="rulesDraft.mode !== 'none'">
                        <label class="form-label">Minimum hours</label>
                        <input type="text" class="form-control font-monospace mb-2" x-model="rulesDraft.hours" inputmode="decimal">
                        <label class="form-label">Shortfall rate</label>
                        <select class="form-select mb-2" x-model="rulesDraft.shortfall_rate_source">
                            <option value="standard_mechanical">Default Labor Rate (Mechanical × Retail)</option>
                            <option value="custom">Custom Rate</option>
                        </select>
                        <div class="input-group" x-show="rulesDraft.shortfall_rate_source === 'custom'">
                            <span class="input-group-text">$</span>
                            <input type="text" class="form-control font-monospace" x-model="rulesDraft.shortfall_rate" inputmode="decimal">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" @click="closeRules()">Cancel</button>
                    <button type="button" class="btn btn-primary" @click="saveRules()">Save rules</button>
                </div>
            </div>
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
        rulesColumn: null,
        rulesDraft: { mode: 'none', hours: '0.00', shortfall_rate_source: 'standard_mechanical', shortfall_rate: '' },
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
        syncWorkTypeUrl(push) {
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
        defaultRateInput() {
            const cents = this.matrix.standard_rate_cents;
            return cents == null ? '' : (cents / 100).toFixed(2);
        },
        inheritCount() {
            let count = 0;
            Object.values(this.matrix.cells || {}).forEach((row) => {
                Object.values(row || {}).forEach((cell) => {
                    if (cell?.settings_mode === 'standard' || cell?.follows_shop_standard) {
                        count += 1;
                    }
                });
            });
            return count === 1 ? '1' : String(count);
        },
        async saveDefaultLaborRate(value) {
            const cents = Math.round(parseFloat(value || '0') * 100);
            const rate = Number.isFinite(cents) ? (cents / 100).toFixed(2) : '0.00';
            this.matrix.standard_rate_cents = Math.round(parseFloat(rate) * 100);
            await this.request(this.matrix.routes.rate, 'PATCH', {
                work_type: 'mechanical',
                billing_class: 'customer_pay',
                mode: 'custom',
                rate,
            });
        },
        cell(workType, billingClass) {
            this.matrix.cells[workType] ||= {};
            this.matrix.cells[workType][billingClass] ||= {
                settings_mode: 'custom',
                effective_rate_cents: null,
                rate_display: '—',
            };
            return this.matrix.cells[workType][billingClass];
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
            await this.request(this.matrix.routes.rate, 'PATCH', {
                work_type: workType,
                billing_class: billingClass,
                mode: cell.settings_mode || 'custom',
                rate: ((cell.effective_rate_cents || 0) / 100).toFixed(2),
            });
        },
        openRules(column) {
            this.addDialog = null;
            this.rulesColumn = column;
            const row = this.matrix.billing_minimums[column.value] || {};
            this.rulesDraft = {
                mode: row.mode || 'none',
                hours: row.hours || '0.00',
                shortfall_rate_source: row.shortfall_rate_source || 'standard_mechanical',
                shortfall_rate: row.shortfall_rate_cents != null ? (row.shortfall_rate_cents / 100).toFixed(2) : '',
            };
        },
        closeRules() {
            this.rulesColumn = null;
        },
        async saveRules() {
            if (!this.rulesColumn) return;
            await this.request(this.matrix.routes.minimum, 'PATCH', {
                billing_class: this.rulesColumn.value,
                ...this.rulesDraft,
            });
            this.closeRules();
        },
        promptAddWorkType() {
            this.rulesColumn = null;
            this.addDialogName = '';
            this.addDialog = {
                kind: 'work_type',
                title: 'Add work type',
                label: 'Work type name',
                hint: 'Creates a selectable work type with rates for every billing class. Applies to new repair orders only.',
            };
        },
        promptAddLaborColumn() {
            this.rulesColumn = null;
            this.addDialogName = '';
            this.addDialog = {
                kind: 'billing_class',
                title: 'Add labor column',
                label: 'Labor column name',
                hint: 'Creates a labor-pricing column with its own rates and Rules. Does not create a customer Billing Class — use Manage Billing Classes for that.',
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
