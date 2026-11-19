{{--
  Shared Billing Classes authority UI.
  Class Settings is the single entry for existing classes; Add Billing Class lives on the Labor matrix.
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
    @labor-open-create-billing-class.window="openCreate()"
>
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
                            <div class="col-12 col-md-6">
                                <label class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" x-model="draft.is_default">
                                    <span class="form-check-label">Shop Default (new customers)</span>
                                </label>
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

                    <div class="mb-3">
                        <div class="subheader mb-2">Warranty</div>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Warranty mode</label>
                                <select class="form-select form-select-sm" x-model="draft.warranty_mode">
                                    <template x-for="mode in (catalog.warranty_modes || [])" :key="'wm-'+mode.value">
                                        <option :value="mode.value" x-text="mode.label"></option>
                                    </template>
                                </select>
                            </div>
                            <template x-if="draft.warranty_mode === 'class_specific'">
                                <div class="col-12 col-md-6">
                                    <label class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" x-model="draft.warranty.third_party">
                                        <span class="form-check-label">Third-party administrator</span>
                                    </label>
                                </div>
                            </template>
                            <template x-if="draft.warranty_mode === 'class_specific'">
                                <div class="col-6 col-md-3">
                                    <label class="form-label mb-1">Months</label>
                                    <input type="number" min="0" class="form-control form-control-sm" x-model="draft.warranty.months">
                                </div>
                            </template>
                            <template x-if="draft.warranty_mode === 'class_specific'">
                                <div class="col-6 col-md-3">
                                    <label class="form-label mb-1">Miles</label>
                                    <input type="number" min="0" class="form-control form-control-sm" x-model="draft.warranty.miles">
                                </div>
                            </template>
                            <template x-if="draft.warranty_mode === 'class_specific'">
                                <div class="col-12 col-md-6">
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" x-model="draft.warranty.covers_parts">
                                        <span class="form-check-label">Covers parts</span>
                                    </label>
                                    <label class="form-check ms-3">
                                        <input class="form-check-input" type="checkbox" x-model="draft.warranty.covers_labor">
                                        <span class="form-check-label">Covers labor</span>
                                    </label>
                                </div>
                            </template>
                            <template x-if="draft.warranty_mode === 'class_specific'">
                                <div class="col-12">
                                    <label class="form-label mb-1">Description</label>
                                    <textarea class="form-control form-control-sm" rows="2" x-model="draft.warranty.description"></textarea>
                                </div>
                            </template>
                            <template x-if="draft.warranty_mode === 'class_specific'">
                                <div class="col-12">
                                    <label class="form-label mb-1">Exclusions</label>
                                    <textarea class="form-control form-control-sm" rows="2" x-model="draft.warranty.exclusions"></textarea>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="subheader mb-2">Document terms</div>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Terms mode</label>
                                <select class="form-select form-select-sm" x-model="draft.document_terms_mode">
                                    <template x-for="mode in (catalog.document_terms_modes || [])" :key="'dtm-'+mode.value">
                                        <option :value="mode.value" x-text="mode.label"></option>
                                    </template>
                                </select>
                            </div>
                            <template x-if="draft.document_terms_mode !== 'shop_defaults'">
                                <div class="col-12">
                                    <label class="form-label mb-1">Class terms</label>
                                    <textarea class="form-control form-control-sm" rows="3" x-model="draft.document_terms" placeholder="Shown on estimates and invoices for concerns on this class"></textarea>
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
                        Legacy unlinked class — open Save after linking from Add Billing Class, or archive if unused.
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
            const url = new URL(window.location.href);
            if (url.searchParams.has('manage-billing-classes')) {
                url.searchParams.delete('manage-billing-classes');
                window.history.replaceState({}, '', url);
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
                is_default: false,
                warranty_mode: 'inherit_shop',
                warranty: { months: null, miles: null, covers_parts: false, covers_labor: false, description: '', exclusions: '', third_party: false },
                document_terms_mode: 'shop_defaults',
                document_terms: '',
            };
        },
        openClassSettings(key) {
            if (!key) {
                this.status = 'error';
                this.statusMessage = 'That labor column has no linked customer Billing Class. Use the row actions to archive or delete it.';
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
            const warranty = row.warranty || {};
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
                is_default: !!row.is_default,
                is_customer_only: !!row.is_customer_only,
                warranty_mode: row.warranty_mode || 'inherit_shop',
                warranty: {
                    months: warranty.months ?? null,
                    miles: warranty.miles ?? null,
                    covers_parts: !!warranty.covers_parts,
                    covers_labor: !!warranty.covers_labor,
                    description: warranty.description || '',
                    exclusions: warranty.exclusions || '',
                    third_party: !!warranty.third_party,
                },
                document_terms_mode: row.document_terms_mode || 'shop_defaults',
                document_terms: row.document_terms || '',
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
                window.dispatchEvent(new CustomEvent('labor-matrix-refresh'));
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
                window.dispatchEvent(new CustomEvent('labor-matrix-refresh'));
            } catch (error) {
                this.status = 'error';
                this.statusMessage = error.message || 'Delete failed';
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
            const warrantyProfile = this.billingProfile(draft) === 'warranty';
            return {
                key: draft.key || undefined,
                name: draft.name,
                pill_color: draft.pill_color,
                document_show_part_number: !!draft.document_show_part_number,
                document_show_part_brand: !!draft.document_show_part_brand,
                document_show_vendor: !!draft.document_show_vendor,
                shop_fees_enabled: warrantyProfile ? false : !!draft.shop_fees_enabled,
                shop_fee_rate_override: warrantyProfile || !draft.shop_fees_enabled ? null : (draft.shop_fee_rate_override || null),
                default_parts_matrix_key: draft.default_parts_matrix_key || null,
                discount_type: this.billingProfile(draft) === 'fleet' ? 'none' : (draft.discount_type || 'none'),
                discount_amount: this.billingProfile(draft) === 'fleet' ? null : draft.discount_amount,
                labor_column_key: draft.labor_column_key || null,
                is_default: !!draft.is_default,
                warranty_mode: draft.warranty_mode || 'inherit_shop',
                warranty: draft.warranty_mode === 'class_specific' ? {
                    months: draft.warranty?.months === '' || draft.warranty?.months === null ? null : Number(draft.warranty.months),
                    miles: draft.warranty?.miles === '' || draft.warranty?.miles === null ? null : Number(draft.warranty.miles),
                    covers_parts: !!draft.warranty?.covers_parts,
                    covers_labor: !!draft.warranty?.covers_labor,
                    description: draft.warranty?.description || '',
                    exclusions: draft.warranty?.exclusions || '',
                    third_party: !!draft.warranty?.third_party,
                } : null,
                document_terms_mode: draft.document_terms_mode || 'shop_defaults',
                document_terms: draft.document_terms_mode === 'shop_defaults' ? null : (draft.document_terms || null),
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
                window.dispatchEvent(new CustomEvent('labor-matrix-refresh'));
            } catch (error) {
                this.status = 'error';
                this.statusMessage = error.message || 'Not saved';
            }
        },
    }));
});
</script>
