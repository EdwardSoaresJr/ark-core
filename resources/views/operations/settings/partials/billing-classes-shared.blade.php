{{--
  Shared Billing Classes authority UI (#180 Phase 5).
  One Alpine component: Labor Class Settings + Manage Billing Classes.
  No Financial Rules tab panel — deep links open Labor and this Manage modal.
--}}
@php
    $billingClassesBootstrap = app(\App\Ark\Operations\Settings\PersistCustomerBillingClasses::class)
        ->catalog($settings);
@endphp

<div
    data-billing-classes-shared
    style="display: contents"
    x-data="arkBillingClasses(@js($billingClassesBootstrap))"
    @labor-open-class-settings.window="openClassSettings($event.detail?.key)"
    @labor-open-manage-billing-classes.window="openManage()"
>
    {{-- Manage Billing Classes modal --}}
    <div
        class="modal modal-blur"
        tabindex="-1"
        data-billing-classes-manage-modal
        x-cloak
        :class="{ show: manageOpen }"
        :style="manageOpen ? 'display: block !important; background: rgba(22, 28, 45, 0.35);' : 'display: none !important'"
    >
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Customer-only &amp; archived classes</h5>
                        <div class="text-secondary small">
                            Labor-linked classes edit from the Class Settings gear on the Labor matrix. This list covers customer-only classes, archived customer classes, and labor-only columns.
                        </div>
                    </div>
                    <button type="button" class="btn-close" @click="closeManage()"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="small" aria-live="polite">
                            <span class="text-success" x-show="status === 'saved'" x-text="statusMessage || 'Saved'"></span>
                            <span class="text-danger" x-show="status === 'error'" x-text="statusMessage || 'Not saved'"></span>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" @click="openCreate()">+ Add Billing Class</button>
                    </div>
                    <div class="subheader mb-2">Customer Billing Classes</div>
                    <div class="list-group list-group-flush mb-3">
                        <template x-for="row in (catalog.manage_classes || [])" :key="'manage-'+row.key">
                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" @click="openClassSettings(row.key)">
                                <span>
                                    <span class="fw-medium" x-text="row.name"></span>
                                    <span class="badge bg-secondary-lt ms-1" x-show="!row.is_active">Archived</span>
                                    <span
                                        class="form-hint d-block mb-0"
                                        x-text="row.is_customer_only ? 'Customer only — no labor column' : ('Linked labor pricing · ' + row.name)"
                                    ></span>
                                </span>
                                <span class="btn btn-outline-primary btn-sm">Class Settings</span>
                            </button>
                        </template>
                        <div class="list-group-item text-secondary small" x-show="!(catalog.manage_classes || []).length">
                            No customer-only or archived classes. Labor-linked classes use the Class Settings gear on each Labor matrix row.
                        </div>
                    </div>
                    <div class="subheader mb-2" x-show="(catalog.labor_only_columns || []).length">Labor-only columns</div>
                    <div class="list-group list-group-flush" x-show="(catalog.labor_only_columns || []).length">
                        <template x-for="column in (catalog.labor_only_columns || [])" :key="'labor-only-'+column.key">
                            <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                <span>
                                    <span class="fw-medium" x-text="column.label"></span>
                                    <span class="badge bg-secondary-lt ms-1" x-show="!column.is_active">Archived</span>
                                    <span class="form-hint d-block mb-0">Labor rates only — no customer Billing Class</span>
                                </span>
                                <div class="btn-list flex-nowrap">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" x-show="column.is_active" @click="archiveLaborColumn(column.key)">Archive</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" x-show="!column.is_active" @click="restoreLaborColumn(column.key)">Restore</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" @click="deleteLaborColumn(column.key)">Delete</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" @click="closeManage()">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Class Settings / create modal (single instance) --}}
    <div
        class="modal modal-blur"
        tabindex="-1"
        data-billing-class-settings-modal
        x-cloak
        :class="{ show: !!draft }"
        :style="draft ? 'display: block !important; background: rgba(22, 28, 45, 0.45);' : 'display: none !important'"
    >
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content" x-show="draft">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0" x-text="draft?.key ? ('Class Settings · ' + draft.name) : 'Add Billing Class'"></h5>
                        <div class="text-secondary small">
                            Shared commercial settings for all work types. Hourly rates, Min Hrs, and Round To stay on the Labor matrix row.
                        </div>
                    </div>
                    <button type="button" class="btn-close" @click="closeEditor()"></button>
                </div>
                <div class="modal-body" x-show="draft">
                    <div class="alert alert-danger small" x-show="Object.keys(errors).length" x-cloak>
                        <template x-for="(messages, field) in errors" :key="field">
                            <div x-text="Array.isArray(messages) ? messages[0] : messages"></div>
                        </template>
                    </div>

                    <div class="mb-3">
                        <div class="subheader mb-2">Identity</div>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Billing class name</label>
                                <input type="text" class="form-control form-control-sm" x-model="draft.name" maxlength="255">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Pill color</label>
                                <div class="d-flex align-items-center gap-2">
                                    <select class="form-select form-select-sm" x-model="draft.pill_color">
                                        <template x-for="color in catalog.pill_colors" :key="color.key">
                                            <option :value="color.key" x-text="color.label"></option>
                                        </template>
                                    </select>
                                    <span class="badge" :class="pillBadgeClass(draft)" x-text="draft.name || 'Preview'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="subheader mb-2">Pricing</div>
                        <template x-if="billingProfile(draft) === 'warranty'">
                            <p class="form-hint mb-0">Warranty never collects shop fees. Parts matrix is under Defaults.</p>
                        </template>
                        <template x-if="billingProfile(draft) !== 'warranty'">
                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label mb-1" x-text="billingProfile(draft) === 'fleet' ? 'Fleet shop fees' : 'Shop fees'"></label>
                                    <select
                                        class="form-select form-select-sm"
                                        :value="draft.shop_fees_enabled ? '1' : '0'"
                                        @change="draft.shop_fees_enabled = $event.target.value === '1'"
                                    >
                                        <option value="1">Use shop rate</option>
                                        <option value="0">None</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label mb-1" x-text="billingProfile(draft) === 'fleet' ? 'Fleet fee rate override %' : 'Fee rate override %'"></label>
                                    <input type="text" class="form-control form-control-sm" inputmode="decimal" placeholder="Shop default" x-model="draft.shop_fee_rate_override" :disabled="! draft.shop_fees_enabled">
                                </div>
                                <template x-if="billingProfile(draft) !== 'fleet'">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label mb-1">Standing discount</label>
                                        <select class="form-select form-select-sm" x-model="draft.discount_type">
                                            <option value="none">None</option>
                                            <option value="labor">Labor</option>
                                            <option value="parts">Parts</option>
                                            <option value="both">Labor &amp; parts</option>
                                        </select>
                                    </div>
                                </template>
                                <template x-if="billingProfile(draft) !== 'fleet'">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label mb-1">Discount %</label>
                                        <input type="text" class="form-control form-control-sm" inputmode="decimal" x-model="draft.discount_amount" placeholder="No discount">
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="mb-3">
                        <div class="subheader mb-2">Defaults</div>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1" x-text="billingProfile(draft) === 'warranty' ? 'Warranty parts matrix' : (billingProfile(draft) === 'fleet' ? 'Fleet parts matrix' : 'Default parts matrix')"></label>
                                <select class="form-select form-select-sm" x-model="draft.default_parts_matrix_key">
                                    <option value="">Shop default</option>
                                    <template x-for="matrix in catalog.parts_matrices" :key="'m-'+matrix.key">
                                        <option :value="matrix.key" x-text="matrix.name"></option>
                                    </template>
                                </select>
                            </div>
                            <template x-if="billingProfile(draft) !== 'fleet' && billingProfile(draft) !== 'warranty'">
                                <div class="col-12 col-md-6">
                                    <label class="form-label mb-1">New concerns bill as</label>
                                    <select class="form-select form-select-sm" x-model="draft.concern_billing_posture">
                                        <option value="">Shop default (from the class name)</option>
                                        <template x-for="posture in catalog.postures" :key="posture.value">
                                            <option :value="posture.value" x-text="posture.label"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="subheader mb-2">Customer Documents</div>
                        <p class="form-hint mb-2">Part description is always shown on customer estimates and invoices. Choose optional identifiers for this billing class only.</p>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="billing-doc-part-number" x-model="draft.document_show_part_number">
                            <label class="form-check-label" for="billing-doc-part-number">Show part number</label>
                        </div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="billing-doc-part-brand" x-model="draft.document_show_part_brand">
                            <label class="form-check-label" for="billing-doc-part-brand">Show part brand</label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="billing-doc-vendor" x-model="draft.document_show_vendor">
                            <label class="form-check-label" for="billing-doc-vendor">Show vendor/supplier</label>
                        </div>
                    </div>

                    <p class="form-hint mb-0 mt-3" x-show="draft.labor_column_key" :title="'Key: ' + draft.labor_column_key">
                        Linked labor pricing: <span x-text="draft.name"></span>
                    </p>
                    <p class="form-hint mb-0 mt-3" x-show="!draft.labor_column_key">
                        Customer-only class — no Labor matrix column. Use Add Labor Column on Labor if you need rates for a separate posture.
                    </p>
                </div>
                <div class="modal-footer justify-content-between">
                    <div class="btn-list" x-show="draft?.key && !creating">
                        <button type="button" class="btn btn-outline-secondary btn-sm" x-show="draft?.is_active !== false" @click="archiveDraft()">Archive</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" x-show="draft?.is_active === false" @click="restoreDraft()">Restore</button>
                        <button type="button" class="btn btn-outline-danger btn-sm" @click="deleteDraft()">Delete</button>
                    </div>
                    <div class="btn-list ms-auto">
                        <button type="button" class="btn" @click="closeEditor()">Cancel</button>
                        <button type="button" class="btn btn-primary" @click="saveDraft()" :disabled="status === 'saving'">
                            <span x-text="status === 'saving' ? 'Saving…' : 'Save'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('arkBillingClasses', (initial) => ({
        catalog: initial,
        manageOpen: false,
        draft: null,
        creating: false,
        status: 'idle',
        statusMessage: '',
        errors: {},
        init() {
            this.$watch('status', (value) => {
                if (value === 'saved') {
                    setTimeout(() => { if (this.status === 'saved') this.status = 'idle'; }, 1800);
                }
            });
            const shop = this.shopSettings();
            if (shop?.openManageBillingClassesOnLoad) {
                shop.openManageBillingClassesOnLoad = false;
                this.$nextTick(() => this.openManage());
            }
        },
        shopSettings() {
            const el = this.$el.closest('[data-shop-settings]');
            return el ? Alpine.$data(el) : null;
        },
        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },
        billingProfile(row) {
            const name = (row?.name || '').toLowerCase().trim();
            if (name === 'fleet') return 'fleet';
            if (name === 'warranty') return 'warranty';
            return 'standard';
        },
        pillBadgeClass(row) {
            const map = {
                slate: 'bg-secondary-lt',
                blue: 'bg-blue-lt',
                green: 'bg-green-lt',
                amber: 'bg-yellow-lt',
                orange: 'bg-orange-lt',
                rose: 'bg-red-lt',
                violet: 'bg-purple-lt',
            };
            return map[row?.pill_color] || 'bg-secondary-lt';
        },
        openManage() {
            this.manageOpen = true;
            this.refreshCatalog();
            const url = new URL(window.location.href);
            if (url.searchParams.get('manage-billing-classes') === '1') {
                url.searchParams.delete('manage-billing-classes');
                window.history.replaceState({}, '', url);
            }
        },
        closeManage() {
            this.manageOpen = false;
        },
        openCreate() {
            this.creating = true;
            this.errors = {};
            this.draft = {
                key: null,
                name: '',
                pill_color: 'slate',
                document_show_part_number: false,
                document_show_part_brand: false,
                document_show_vendor: false,
                shop_fees_enabled: true,
                shop_fee_rate_override: null,
                default_parts_matrix_key: '',
                discount_type: 'none',
                discount_amount: null,
                concern_billing_posture: '',
                labor_column_key: null,
            };
        },
        openClassSettings(key) {
            if (!key) {
                this.status = 'error';
                this.statusMessage = 'That labor column has no linked customer Billing Class. Use Manage Billing Classes.';
                this.openManage();
                return;
            }
            const row = (this.catalog.classes || []).find((item) => item.key === key);
            if (!row) {
                this.refreshCatalog().then(() => {
                    const again = (this.catalog.classes || []).find((item) => item.key === key);
                    if (!again) {
                        this.status = 'error';
                        this.statusMessage = 'Billing class not found.';
                        return;
                    }
                    this.creating = false;
                    this.errors = {};
                    this.draft = this.cloneDraft(again);
                });
                return;
            }
            this.creating = false;
            this.errors = {};
            this.draft = this.cloneDraft(row);
        },
        cloneDraft(row) {
            return {
                key: row.key,
                name: row.name,
                pill_color: row.pill_color || 'slate',
                document_show_part_number: !!row.document_show_part_number,
                document_show_part_brand: !!row.document_show_part_brand,
                document_show_vendor: !!row.document_show_vendor,
                shop_fees_enabled: !!row.shop_fees_enabled,
                shop_fee_rate_override: row.shop_fee_rate_override,
                default_parts_matrix_key: row.default_parts_matrix_key || '',
                discount_type: row.discount_type || 'none',
                discount_amount: row.discount_amount,
                concern_billing_posture: row.concern_billing_posture || '',
                labor_column_key: row.labor_column_key || null,
                is_active: row.is_active !== false,
                is_customer_only: !!row.is_customer_only,
            };
        },
        lifecycleUrl(template, key) {
            return String(template || '').replace('__KEY__', encodeURIComponent(key));
        },
        async postLifecycle(url, successMessage) {
            this.status = 'saving';
            this.errors = {};
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.errors = payload.errors || { form: [payload.message || successMessage] };
                    this.status = 'error';
                    this.statusMessage = Array.isArray(this.errors.key) ? this.errors.key[0] : (payload.message || 'Request failed');
                    if (Array.isArray(this.errors.references)) {
                        this.statusMessage += ' — ' + this.errors.references.join('; ');
                    }
                    return false;
                }
                if (payload.catalog) this.catalog = payload.catalog;
                this.status = 'saved';
                this.statusMessage = payload.status || successMessage;
                this.$dispatch('labor-matrix-refresh');
                return true;
            } catch (error) {
                this.status = 'error';
                this.statusMessage = error.message || 'Request failed';
                return false;
            }
        },
        async archiveDraft() {
            if (!this.draft?.key) return;
            if (!window.confirm('Archive “' + this.draft.name + '”? It will leave new customer selections but keep historical records.')) return;
            if (await this.postLifecycle(this.lifecycleUrl(this.catalog.routes?.archive, this.draft.key), 'Archived')) {
                this.closeEditor();
            }
        },
        async restoreDraft() {
            if (!this.draft?.key) return;
            if (await this.postLifecycle(this.lifecycleUrl(this.catalog.routes?.restore, this.draft.key), 'Restored')) {
                this.closeEditor();
            }
        },
        async deleteDraft() {
            if (!this.draft?.key) return;
            if (!window.confirm('Delete “' + this.draft.name + '” only if it was never used? Referenced classes must be archived instead.')) return;
            const url = this.lifecycleUrl(this.catalog.routes?.destroy, this.draft.key);
            this.status = 'saving';
            try {
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.errors = payload.errors || { form: [payload.message || 'Delete refused'] };
                    this.status = 'error';
                    this.statusMessage = Array.isArray(this.errors.key) ? this.errors.key[0] : (payload.message || 'Delete refused');
                    if (Array.isArray(this.errors.references)) {
                        this.statusMessage += ' — ' + this.errors.references.join('; ');
                    }
                    return;
                }
                if (payload.catalog) this.catalog = payload.catalog;
                this.status = 'saved';
                this.statusMessage = payload.status || 'Deleted';
                this.closeEditor();
                this.$dispatch('labor-matrix-refresh');
            } catch (error) {
                this.status = 'error';
                this.statusMessage = error.message || 'Delete failed';
            }
        },
        async archiveLaborColumn(key) {
            if (!window.confirm('Archive this labor-only column? Existing repair orders keep their rates.')) return;
            await this.postLaborColumnLifecycle(this.catalog.routes?.labor_column_archive, key);
        },
        async restoreLaborColumn(key) {
            await this.postLaborColumnLifecycle(this.catalog.routes?.labor_column_restore, key);
        },
        async deleteLaborColumn(key) {
            if (!window.confirm('Delete this labor-only column only if it was never used on a repair order?')) return;
            await this.postLaborColumnLifecycle(this.catalog.routes?.labor_column_destroy, key);
        },
        async postLaborColumnLifecycle(url, key) {
            this.status = 'saving';
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ billing_class: key }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.status = 'error';
                    this.statusMessage = payload.message || (payload.errors?.billing_class?.[0]) || 'Request failed';
                    if (Array.isArray(payload.errors?.references)) {
                        this.statusMessage += ' — ' + payload.errors.references.join('; ');
                    }
                    return;
                }
                await this.refreshCatalog();
                this.status = 'saved';
                this.statusMessage = payload.status || 'Updated';
                this.$dispatch('labor-matrix-refresh');
            } catch (error) {
                this.status = 'error';
                this.statusMessage = error.message || 'Request failed';
            }
        },
        closeEditor() {
            this.draft = null;
            this.creating = false;
            this.errors = {};
        },
        async refreshCatalog() {
            this.status = 'loading';
            try {
                const response = await fetch(@js(route('operations.settings.shop.customer-types.show')), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await response.json();
                if (!response.ok) throw new Error('Unable to load billing classes');
                this.catalog = payload;
                this.status = 'idle';
            } catch (error) {
                this.status = 'error';
                this.statusMessage = error.message || 'Unable to load billing classes';
            }
        },
        payloadFromDraft() {
            const draft = this.draft;
            const warranty = this.billingProfile(draft) === 'warranty';
            return {
                key: draft.key || undefined,
                name: draft.name,
                pill_color: draft.pill_color,
                document_show_part_number: !!draft.document_show_part_number,
                document_show_part_brand: !!draft.document_show_part_brand,
                document_show_vendor: !!draft.document_show_vendor,
                shop_fees_enabled: warranty ? false : !!draft.shop_fees_enabled,
                shop_fee_rate_override: warranty || !draft.shop_fees_enabled ? null : (draft.shop_fee_rate_override || null),
                default_parts_matrix_key: draft.default_parts_matrix_key || null,
                discount_type: this.billingProfile(draft) === 'fleet' ? 'none' : (draft.discount_type || 'none'),
                discount_amount: this.billingProfile(draft) === 'fleet' ? null : draft.discount_amount,
                concern_billing_posture: this.billingProfile(draft) === 'fleet' || this.billingProfile(draft) === 'warranty'
                    ? null
                    : (draft.concern_billing_posture || null),
                labor_column_key: draft.labor_column_key || null,
            };
        },
        async saveDraft() {
            if (!this.draft) return;
            this.status = 'saving';
            this.errors = {};
            const creating = this.creating || !this.draft.key;
            const url = creating
                ? @js(route('operations.settings.shop.customer-types.store'))
                : @js(route('operations.settings.shop.customer-types.item.update', ['classKey' => '__KEY__'])).replace('__KEY__', encodeURIComponent(this.draft.key));
            try {
                const response = await fetch(url, {
                    method: creating ? 'POST' : 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.payloadFromDraft()),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.errors = payload.errors || { form: [payload.message || 'Save failed'] };
                    this.status = 'error';
                    this.statusMessage = payload.message || 'Not saved';
                    return;
                }
                if (payload.catalog) this.catalog = payload.catalog;
                this.status = 'saved';
                this.statusMessage = payload.status || 'Saved';
                this.closeEditor();
            } catch (error) {
                this.status = 'error';
                this.statusMessage = error.message || 'Not saved';
            }
        },
    }));
});
</script>
