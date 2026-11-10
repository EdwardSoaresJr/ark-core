@php
    $matrix = $laborWorkTypeMatrix ?? [
        'work_types' => [],
        'columns' => [],
        'cells' => [],
        'billing_minimums' => [],
        'labor_pricing_profile_version' => 0,
        'routes' => [],
    ];
    $initialWorkType = $matrix['work_types'][0]['key'] ?? 'mechanical';
@endphp

<div
    class="labor-matrix"
    data-settings-labor-matrix
    data-labor-matrix
    x-data="arkLaborMatrix(@js($matrix))"
    x-cloak
>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h3 class="mb-1">Labor rate matrix</h3>
            <p class="text-secondary small mb-0">
                Select a work type, edit hourly rates inline, and open Rules for billing-class minimums.
                Mechanical × Retail is the shop default. Changes apply to new repair orders only.
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

    <div class="row g-3">
        <div class="col-12 col-lg-3">
            <div class="list-group list-group-flush border rounded" data-labor-work-type-nav>
                <template x-for="workType in matrix.work_types" :key="workType.key">
                    <button
                        type="button"
                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                        :class="{ active: selectedWorkType === workType.key }"
                        @click="selectedWorkType = workType.key"
                        :data-labor-work-type="workType.key"
                    >
                        <span x-text="workType.label"></span>
                        <span class="badge bg-azure-lt text-azure" x-show="workType.is_default">Default</span>
                    </button>
                </template>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2" @click="promptAddWorkType()">
                + Add Work Type
            </button>
        </div>

        <div class="col-12 col-lg-9">
            <div class="table-responsive border rounded">
                <table class="table table-vcenter table-sm mb-0" data-labor-rate-editor>
                    <thead>
                        <tr>
                            <th class="bg-light">Billing class</th>
                            <th class="bg-light text-end" style="width: 11rem">Hourly rate</th>
                            <th class="bg-light text-end" style="width: 6rem">Rules</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="column in matrix.columns" :key="column.value">
                            <tr :data-billing-class="column.value">
                                <td>
                                    <div class="fw-medium" x-text="column.label"></div>
                                    <div class="form-hint mb-0" x-show="selectedWorkType === 'mechanical' && column.value === 'customer_pay'">
                                        Shop default hourly rate
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column align-items-end gap-1">
                                        <select
                                            class="form-select form-select-sm"
                                            style="max-width: 9.5rem"
                                            x-model="cell(selectedWorkType, column.value).settings_mode"
                                            @change="saveRate(selectedWorkType, column.value)"
                                            :aria-label="`${selectedWorkTypeLabel()} ${column.label} rate mode`"
                                        >
                                            <option value="standard">Use Default</option>
                                            <option value="custom">Custom</option>
                                            <option value="zero">$0.00</option>
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
                                        <div class="form-hint mb-0 font-monospace" x-show="cell(selectedWorkType, column.value).settings_mode !== 'custom'" x-text="cell(selectedWorkType, column.value).rate_display"></div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="openRules(column)"
                                    >
                                        Rules
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" @click="promptAddBillingClass()">
                + Add Billing Class
            </button>
        </div>
    </div>

    <div
        class="modal modal-blur fade"
        tabindex="-1"
        x-ref="rulesModal"
        data-labor-rules-modal
        :class="{ show: !!rulesColumn }"
        :style="rulesColumn ? 'display:block' : 'display:none'"
        x-show="!!rulesColumn"
        x-cloak
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
                            <option value="standard_mechanical">Mechanical × Retail (default)</option>
                            <option value="custom">Custom rate</option>
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
    <div class="modal-backdrop fade show" x-show="!!rulesColumn || !!addDialog" x-cloak></div>

    <div
        class="modal modal-blur fade"
        tabindex="-1"
        data-labor-add-dialog
        :class="{ show: !!addDialog }"
        :style="addDialog ? 'display:block' : 'display:none'"
        x-show="!!addDialog"
        x-cloak
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
    Alpine.data('arkLaborMatrix', (initial) => ({
        matrix: initial,
        selectedWorkType: initial.work_types?.[0]?.key || 'mechanical',
        status: 'idle',
        lastRequest: null,
        rulesColumn: null,
        rulesDraft: { mode: 'none', hours: '0.00', shortfall_rate_source: 'standard_mechanical', shortfall_rate: '' },
        addDialog: null,
        addDialogName: '',
        init() {
            this.$watch('status', (value) => {
                if (value === 'saved') {
                    setTimeout(() => { if (this.status === 'saved') this.status = 'idle'; }, 1800);
                }
            });
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
        promptAddBillingClass() {
            this.rulesColumn = null;
            this.addDialogName = '';
            this.addDialog = {
                kind: 'billing_class',
                title: 'Add billing class',
                label: 'Billing class name',
                hint: 'Example: Extended Warranty. Creates a shop-scoped class with its own rates and minimums.',
            };
        },
        async confirmAddDialog() {
            const name = (this.addDialogName || '').trim();
            if (!name || !this.addDialog) return;
            const kind = this.addDialog.kind;
            this.addDialog = null;
            if (kind === 'work_type') {
                const payload = await this.request(this.matrix.routes.work_type, 'POST', { name });
                if (payload.work_type?.key) {
                    this.selectedWorkType = payload.work_type.key;
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
