<x-operations.app title="Shop Settings" ui="tabler">
    <x-tabler.page layout="fluid" title="Shop Settings" pretitle="System" subtitle="How this shop runs: shop details, pricing, documents, workflow, staff, and connected services.">
    <section
        x-data="{
            active: (() => {
                const candidate = @js($initialSection) || new URLSearchParams(window.location.search).get('section') || localStorage.getItem('ark:shop-settings:section') || 'general';

                if (candidate === 'dragon-memory' || (@js(\App\Ark\Runtime\DemoInstall::isDemo()) && candidate === 'ark-cloud')) {
                    return 'general';
                }

                return candidate;
            })(),
            financialTab: (() => {
                const fromUrl = new URLSearchParams(window.location.search).get('financial-tab');

                if (fromUrl === 'billing-classes' || fromUrl === 'customer-types' || fromUrl === 'customer-tags') {
                    return 'billing-classes';
                }

                if (['labor', 'labor-policies', 'tax', 'shop-fees', 'deposits', 'parts'].includes(fromUrl)) {
                    return fromUrl;
                }

                const stored = localStorage.getItem('ark:shop-settings:financial-tab') || 'labor';

                if (stored === 'customer-types' || stored === 'customer-tags') {
                    return 'billing-classes';
                }

                return stored;
            })(),
            printingTab: localStorage.getItem('ark:shop-settings:printing-tab') || 'printers',
            communicationsTab: (() => {
                const fromUrl = new URLSearchParams(window.location.search).get('communications-tab');

                if (fromUrl) {
                    return fromUrl;
                }

                return localStorage.getItem('ark:shop-settings:communications-tab') || 'general';
            })(),
            workflowTab: (() => {
                const fromUrl = new URLSearchParams(window.location.search).get('workflow-tab');

                if (fromUrl === 'defaults' || fromUrl === 'statuses' || fromUrl === 'inspections' || fromUrl === 'saved-work') {
                    return fromUrl;
                }

                return localStorage.getItem('ark:shop-settings:workflow-tab') || 'defaults';
            })(),
            nextCustomerTypeIndex: {{ count($settings->customerTypeRows()) }},
            defaultPartsMatrixKey: '{{ $settings->defaultPartsMatrix()['key'] }}',
            setActive(section) {
                this.active = section;
                localStorage.setItem('ark:shop-settings:section', section);
                const url = new URL(window.location.href);
                url.searchParams.set('section', section);
                window.history.replaceState({}, '', url);
            },
            setFinancialTab(tab) {
                this.financialTab = tab;
                localStorage.setItem('ark:shop-settings:financial-tab', tab);
                const url = new URL(window.location.href);
                url.searchParams.set('financial-tab', tab);
                window.history.replaceState({}, '', url);
            },
            setPrintingTab(tab) {
                this.printingTab = tab;
                localStorage.setItem('ark:shop-settings:printing-tab', tab);
            },
            setCommunicationsTab(tab) {
                this.communicationsTab = tab;
                localStorage.setItem('ark:shop-settings:communications-tab', tab);
                const url = new URL(window.location.href);
                url.searchParams.set('communications-tab', tab);
                window.history.replaceState({}, '', url);
            },
            setWorkflowTab(tab) {
                this.workflowTab = tab;
                localStorage.setItem('ark:shop-settings:workflow-tab', tab);
                const url = new URL(window.location.href);
                url.searchParams.set('workflow-tab', tab);
                window.history.replaceState({}, '', url);
            },
            autosaveMatrix(matrixKey, statusId, immediate = false) {
                const form = document.getElementById('parts-matrix-form');
                const status = document.getElementById(statusId);

                if (! form) {
                    return;
                }

                clearTimeout(form[`_arkAutosaveTimer_${matrixKey}`]);
                form[`_arkAutosaveTimer_${matrixKey}`] = setTimeout(() => {
                    if (status) {
                        status.textContent = 'Saving...';
                    }

                    fetch(`/app/settings/shop/parts-matrices/${matrixKey}`, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                    }).then((response) => {
                        if (! response.ok) {
                            throw new Error('Save failed');
                        }

                        if (status) {
                            status.textContent = 'Saved';
                        }
                    }).catch(() => {
                        if (status) {
                            status.textContent = 'Save needed';
                        }
                    });
                }, immediate ? 0 : 700);
            },
            matrixSection(initialRows, matrixName, formId) {
                return {
                    matrixName,
                    rows: initialRows.map((row, index) => Object.assign({}, row, {
                        uid: `${Date.now()}-${index}-${Math.random().toString(36).slice(2)}`,
                    })),
                    deleteConfirming: false,
                    deleteTypedName: '',
                    deleteMatrixName: matrixName,
                    deleteFormId: formId,
                    addRow() {
                        this.rows.push({
                            uid: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
                            min_cost: '',
                            max_cost: '',
                            markup_percentage: '',
                            sort_order: this.rows.length + 1,
                        });
                    },
                    removeRow(index) {
                        if (this.rows.length === 1) {
                            return;
                        }

                        this.rows.splice(index, 1);
                    },
                    openDeleteConfirm() {
                        this.deleteMatrixName = (this.matrixName || '').trim();

                        this.deleteConfirming = true;
                        this.deleteTypedName = '';
                    },
                    cancelDeleteConfirm() {
                        this.deleteConfirming = false;
                        this.deleteTypedName = '';
                    },
                    canConfirmDelete() {
                        return this.deleteTypedName.trim() === this.deleteMatrixName.trim();
                    },
                    submitDelete() {
                        if (! this.canConfirmDelete()) {
                            return;
                        }

                        const form = document.getElementById(this.deleteFormId);

                        if (! form) {
                            return;
                        }

                        let input = form.querySelector('[name=confirm_name]');

                        if (! input) {
                            input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'confirm_name';
                            form.appendChild(input);
                        }

                        input.value = this.deleteTypedName.trim();
                        form.submit();
                    },
                };
            },
        }"
    >
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <div class="fw-bold">Could not save. Check the fields below.</div>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <p class="text-secondary small">Parts matrix changes save as you edit. Every other section has its own Save button.</p>

        @php
            $settingsNav = [
                'Your shop' => [
                    ['key' => 'general', 'label' => 'Shop Identity'],
                    ['key' => 'operations', 'label' => 'Appointments & Setup'],
                    ['key' => 'staff', 'label' => 'Staff', 'can' => App\Ark\Runtime\Authorization\ArkCapability::StaffManage->value],
                ],
                'Money' => [
                    ['key' => 'financial', 'label' => 'Financial Rules'],
                    ['key' => 'overhead', 'label' => 'Shop Overhead'],
                    ['key' => 'excellence', 'label' => 'Owner Targets & Reporting'],
                ],
                'Documents & workflow' => [
                    ['key' => 'estimates', 'label' => 'Documents / Disclaimers'],
                    ['key' => 'workflow', 'label' => 'Workflow Defaults'],
                    ['key' => 'printing', 'label' => 'Label Printing'],
                ],
                'Connected services' => [
                    ['key' => 'payments', 'label' => 'Square Payments'],
                    ['key' => 'partstech', 'label' => 'Parts catalogs'],
                    ['key' => 'communications', 'label' => 'Communications'],
                    ['key' => 'stations', 'label' => 'Stations & Phones', 'href' => route('operations.shop.communications')],
                    ['key' => 'ark-cloud', 'label' => 'ARK Platform', 'hidden' => \App\Ark\Runtime\DemoInstall::isDemo()],
                ],
            ];
            $tablerSections = ['general', 'estimates', 'workflow', 'printing', 'financial', 'overhead', 'operations'];
        @endphp

        <div class="row g-3">
            <div class="col-12 d-lg-none">
                <label for="settings-section" class="form-label">Section</label>
                <select id="settings-section" class="form-select" :value="active" @change="setActive($event.target.value)">
                    @foreach ($settingsNav as $group => $items)
                        <optgroup label="{{ $group }}">
                            @foreach ($items as $item)
                                @continue(isset($item['href']) || ($item['hidden'] ?? false) || (isset($item['can']) && ! auth()->user()?->can($item['can'])))
                                <option value="{{ $item['key'] }}">{{ $item['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-3 col-xxl-2 d-none d-lg-block">
                <nav class="card sticky-lg-top" style="top: 1rem" aria-label="Shop settings sections">
                    @foreach ($settingsNav as $group => $items)
                        <div class="card-header py-2"><div class="subheader">{{ $group }}</div></div>
                        <div class="list-group list-group-flush">
                            @foreach ($items as $item)
                                @continue(($item['hidden'] ?? false) || (isset($item['can']) && ! auth()->user()?->can($item['can'])))
                                @if (isset($item['href']))
                                    <a href="{{ $item['href'] }}" class="list-group-item list-group-item-action">{{ $item['label'] }}</a>
                                @else
                                    <button
                                        type="button"
                                        class="list-group-item list-group-item-action"
                                        :class="{ 'active': active === @js($item['key']) }"
                                        :aria-current="active === @js($item['key']) ? 'page' : null"
                                        @click="setActive(@js($item['key']))"
                                    >{{ $item['label'] }}</button>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </nav>
            </div>

            <div class="col-12 col-lg-9 col-xxl-10">
                <section class="card" x-show="active === 'general'">
                    <div class="card-header">
                        <div>
                            <div class="subheader">Shop Identity</div>
                            <h2 class="card-title">Shop name, logo, and contact details</h2>
                            <div class="card-subtitle">Shown on estimates, invoices, and other customer documents.</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('operations.settings.shop.general.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="row g-3 align-items-center border rounded p-2 mx-0">
                                        <div class="col-12 col-md-3">
                                            <div class="fw-medium">Estimate logo</div>
                                            <div class="text-secondary small">Used on estimate documents. PNG, JPG, or WebP up to 2 MB.</div>
                                        </div>
                                        <div class="col-12 col-sm-4 col-md-3">
                                            <div class="d-flex align-items-center justify-content-center border rounded bg-white p-2" style="min-height: 5rem">
                                                @if ($settings->logo_path)
                                                    <img src="{{ Storage::disk('public')->url($settings->logo_path) }}" alt="{{ ($settings->shop_name ?: config('app.name')).' logo' }}" class="img-fluid" style="max-height: 4rem">
                                                @else
                                                    <span class="subheader">No logo</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm">
                                            <label for="settings-logo" class="form-label">Upload logo</label>
                                            <input id="settings-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="form-control">
                                            @if ($settings->logo_path)
                                                <label class="form-check mt-2 mb-0">
                                                    <input type="checkbox" name="remove_logo" value="1" class="form-check-input">
                                                    <span class="form-check-label">Remove current logo</span>
                                                </label>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-shop-name" class="form-label">Shop name</label>
                                    <input id="settings-shop-name" name="shop_name" value="{{ old('shop_name', $settings->shop_name) }}" class="form-control">
                                </div>
                                <div class="col-12 col-md-6">
                                    @php
                                        $timezoneOptions = timezone_identifiers_list();
                                        $timezoneValue = (string) old('shop_timezone', $settings->shop_timezone ?: \App\Ark\Operations\Settings\ShopSettings::INSTALL_DEFAULT_TIMEZONE);
                                    @endphp
                                    <label for="settings-shop-timezone" class="form-label">Shop timezone</label>
                                    <select id="settings-shop-timezone" name="shop_timezone" required @class(['form-select', 'is-invalid' => $errors->has('shop_timezone')])>
                                        @if ($timezoneValue !== '' && ! in_array($timezoneValue, $timezoneOptions, true))
                                            <option value="{{ $timezoneValue }}" selected>{{ $timezoneValue }} - choose a listed timezone</option>
                                        @endif
                                        @foreach ($timezoneOptions as $tz)
                                            <option value="{{ $tz }}" @selected($timezoneValue === $tz)>{{ $tz }}</option>
                                        @endforeach
                                    </select>
                                    @error('shop_timezone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-hint">All operational timestamps are stored in UTC and displayed in this timezone.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-phone" class="form-label">Phone</label>
                                    <input id="settings-phone" name="phone" value="{{ old('phone', $settings->phone) }}" class="form-control">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-email" class="form-label">Email</label>
                                    <input id="settings-email" name="email" value="{{ old('email', $settings->email) }}" class="form-control">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-website" class="form-label">Website</label>
                                    <input id="settings-website" name="website" value="{{ old('website', $settings->website) }}" placeholder="https://example.com" @class(['form-control', 'is-invalid' => $errors->has('website')])>
                                    @error('website')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label for="settings-address-1" class="form-label">Address line 1</label>
                                    <input id="settings-address-1" name="address_line_1" value="{{ old('address_line_1', $settings->address_line_1) }}" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label for="settings-address-2" class="form-label">Address line 2</label>
                                    <input id="settings-address-2" name="address_line_2" value="{{ old('address_line_2', $settings->address_line_2) }}" class="form-control">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-city" class="form-label">City</label>
                                    <input id="settings-city" name="city" value="{{ old('city', $settings->city) }}" class="form-control">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="settings-state" class="form-label">State</label>
                                    <input id="settings-state" name="state" value="{{ old('state', $settings->state) }}" class="form-control">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="settings-postal-code" class="form-label">Postal code</label>
                                    <input id="settings-postal-code" name="postal_code" value="{{ old('postal_code', $settings->postal_code) }}" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">Save Shop Identity</button>
                        </div>
                    </form>
                </section>
                <section class="card" x-show="active === 'estimates'" x-cloak>
                    <div class="card-header">
                        <div>
                            <div class="subheader">Documents / Disclaimers</div>
                            <h2 class="card-title">Customer-facing document language</h2>
                            <div class="card-subtitle">Global and customer-tag disclaimers copy into snapshots at generation. Invoices keep the language issued at that time.</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('operations.settings.shop.estimates.update') }}">
                        @csrf
                        @method('PATCH')
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <div class="subheader">Global disclaimers</div>
                                    <div class="text-secondary small">Always applied after totals on every estimate and invoice.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="subheader">Billing class disclaimers</div>
                                    <div class="text-secondary small">Applied automatically from the customer&apos;s classification.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="settings-estimate-disclaimer" class="form-label">Global estimate disclaimer</label>
                                <textarea id="settings-estimate-disclaimer" name="estimate_disclaimer" rows="5" class="form-control">{{ old('estimate_disclaimer', $settings->estimate_disclaimer) }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label for="settings-invoice-disclaimer" class="form-label">Global invoice disclaimer</label>
                                <textarea id="settings-invoice-disclaimer" name="invoice_disclaimer" rows="5" class="form-control">{{ old('invoice_disclaimer', $settings->invoice_disclaimer) }}</textarea>
                            </div>

                            <div class="hr-text">Billing class disclaimers</div>
                            <p class="text-secondary small">One disclaimer per configured billing class. Advisors do not pick these manually.</p>
                            @foreach ($settings->customerTypeRows() as $customerType)
                                @php
                                    $typeKey = mb_strtolower($customerType['name']);
                                    $typeDisclaimer = old("customer_type_disclaimers.$typeKey", $settings->customerTypeDisclaimerMap()[$typeKey] ?? '');
                                @endphp
                                <div class="mb-3">
                                    <label for="settings-type-disclaimer-{{ $loop->index }}" class="form-label">{{ $customerType['name'] }}</label>
                                    <textarea id="settings-type-disclaimer-{{ $loop->index }}" name="customer_type_disclaimers[{{ $typeKey }}]" rows="3" class="form-control">{{ $typeDisclaimer }}</textarea>
                                </div>
                            @endforeach

                            <div class="hr-text">Authorization</div>
                            <div class="mb-3">
                                <label for="settings-authorization-language" class="form-label">Authorization language</label>
                                <textarea id="settings-authorization-language" name="authorization_language" rows="3" class="form-control">{{ old('authorization_language', $settings->authorization_language) }}</textarea>
                            </div>
                            <label class="form-check">
                                <input type="checkbox" name="portal_signature_required" value="1" @checked(old('portal_signature_required', $settings->portal_signature_required)) class="form-check-input">
                                <span class="form-check-label">Require customer signature on portal authorization</span>
                                <span class="form-check-description">When enabled, customers must sign and acknowledge authorization language before submitting portal approval.</span>
                            </label>

                            <div class="hr-text">Customer Part Presentation</div>
                            <p class="text-secondary small">Controls how part names appear on estimates, invoices, portal, and print. Full catalog details stay internal for ordering.</p>
                            <div class="mb-2" style="max-width: 28rem">
                                <label for="settings-part-description-mode" class="form-label">Description mode</label>
                                <select id="settings-part-description-mode" name="customer_part_description_mode" class="form-select">
                                    @foreach (\App\Ark\Operations\Parts\CustomerPartDescriptionMode::options() as $mode)
                                        <option value="{{ $mode->value }}" @selected(old('customer_part_description_mode', $settings->customerPartDescriptionMode()->value) === $mode->value)>{{ $mode->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <ul class="text-secondary small mb-3">
                                @foreach (\App\Ark\Operations\Parts\CustomerPartDescriptionMode::options() as $mode)
                                    <li><span class="fw-medium">{{ $mode->label() }}:</span> {{ $mode->helpText() }}</li>
                                @endforeach
                            </ul>
                            @foreach ([
                                'customer_part_show_manufacturer_number' => ['Show manufacturer part number', $settings->customerPartShowManufacturerNumber()],
                                'customer_part_show_supplier' => ['Show supplier name', $settings->customerPartShowSupplier()],
                                'customer_part_show_supplier_sku' => ['Show supplier SKU', $settings->customerPartShowSupplierSku()],
                                'customer_part_allow_description_override' => ['Allow advisor override on individual lines', $settings->customerPartAllowDescriptionOverride()],
                            ] as $field => [$label, $current])
                                <label class="form-check">
                                    <input type="hidden" name="{{ $field }}" value="0">
                                    <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $current)) class="form-check-input">
                                    <span class="form-check-label">{{ $label }}</span>
                                </label>
                            @endforeach
                            <p class="text-secondary small">Billing-class document profiles (Warranty / RepairPal) can still add part number or supplier for audit when those programs need them.</p>

                            <div class="hr-text">Recommendations and validity</div>
                            <div class="mb-3">
                                <label for="settings-recommendation-disclaimer" class="form-label">
                                    Recommendation disclaimer
                                    <span class="text-secondary fw-normal">(concern narrative context, not mixed with authorization)</span>
                                </label>
                                <textarea id="settings-recommendation-disclaimer" name="recommendation_disclaimer" rows="3" class="form-control">{{ old('recommendation_disclaimer', $settings->recommendation_disclaimer) }}</textarea>
                            </div>
                            <div style="max-width: 12rem">
                                <label for="settings-estimate-validity" class="form-label">Estimate validity days</label>
                                <input id="settings-estimate-validity" name="estimate_validity_days" value="{{ old('estimate_validity_days', $settings->estimate_validity_days) }}" required inputmode="numeric" class="form-control">
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">Save Document Settings</button>
                        </div>
                    </form>
                </section>
                <section class="card" x-show="active === 'workflow'" x-cloak data-settings-section="workflow">
                    <div class="card-header">
                        <div>
                            <div class="subheader">Workflow Defaults</div>
                            <h2 class="card-title">Check In defaults and Job Board</h2>
                            <div class="card-subtitle">Shop-wide Check In defaults, Job Board lanes, and the repair order statuses that fill those lanes.</div>
                        </div>
                    </div>
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist" aria-label="Workflow Defaults">
                            @foreach ([
                                'defaults' => 'Check In defaults',
                                'statuses' => 'Job Board',
                                'inspections' => 'Inspection checklists',
                                'saved-work' => 'Common Jobs',
                            ] as $workflowTabKey => $workflowTabLabel)
                                <li class="nav-item" role="presentation">
                                    <button
                                        type="button"
                                        class="nav-link"
                                        role="tab"
                                        id="workflow-tab-{{ $workflowTabKey }}"
                                        aria-controls="workflow-panel-{{ $workflowTabKey }}"
                                        :class="{ 'active': workflowTab === @js($workflowTabKey) }"
                                        :aria-selected="(workflowTab === @js($workflowTabKey)).toString()"
                                        @click="setWorkflowTab(@js($workflowTabKey))"
                                    >{{ $workflowTabLabel }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="card-body" id="workflow-panel-defaults" role="tabpanel" aria-labelledby="workflow-tab-defaults" x-show="workflowTab === 'defaults'" x-cloak>
                        <h3 class="mb-1">Check In defaults</h3>
                        <p class="text-secondary small">Pre-select the visit type, priority, work type, and note visibility for new work.</p>
                        @include('operations.settings.partials.workflow-intake-defaults')
                    </div>

                    <div class="card-body" id="workflow-panel-statuses" role="tabpanel" aria-labelledby="workflow-tab-statuses" x-show="workflowTab === 'statuses'" x-cloak>
                        <h3 class="mb-1">Job Board</h3>
                        <p class="text-secondary small">Set up the shop queues and the statuses that live in each one. Attention stays derived - it is not a lane or a status color.</p>
                        @include('operations.settings.partials.job-board-lanes')
                        <h3 class="mt-5 mb-1">RO statuses</h3>
                        <p class="text-secondary small">Configured operational state for each vehicle. Status color is independent of lane color and of ARK attention.</p>
                        @include('operations.settings.partials.ro-status-catalog')
                    </div>

                    <div class="card-body" id="workflow-panel-inspections" role="tabpanel" aria-labelledby="workflow-tab-inspections" x-show="workflowTab === 'inspections'" x-cloak>
                        <h3 class="mb-1">Inspection checklists</h3>
                        <p class="text-secondary small">Mobile technician checklists - item labels, photo requirements, and measurements. Templates add the checklist rows to each RO. Technicians record findings on each checklist item.</p>
                        @include('operations.settings.partials.inspection-template-settings', ['inspectionTemplates' => $inspectionTemplates])
                    </div>

                    <div class="card-body" id="workflow-panel-saved-work" role="tabpanel" aria-labelledby="workflow-tab-saved-work" x-show="workflowTab === 'saved-work'" x-cloak>
                        <h3 class="mb-1">Common Jobs</h3>
                        <p class="text-secondary small">Repeat jobs that author a Repair Action plus labor, parts, and fees. After Add Work, the template owns nothing - edit the RO normally.</p>
                        @include('operations.settings.partials.work-template-settings', ['workTemplates' => $workTemplates])
                    </div>
                </section>

                <section class="card" x-show="active === 'printing'" x-cloak data-settings-section="printing">
                    <div class="card-header">
                        <div>
                            <div class="subheader">QZ Tray / Brother QL</div>
                            <h2 class="card-title">Key tags and oil change stickers</h2>
                            <div class="card-subtitle">Printer names must match the OS queue exactly. Defaults: Brother QL-800, 62 × 38.1 mm.</div>
                        </div>
                    </div>
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist" aria-label="Label Printing">
                            @foreach (['printers' => 'Printers & labels', 'certificates' => 'Certificates & setup'] as $printingTabKey => $printingTabLabel)
                                <li class="nav-item" role="presentation">
                                    <button
                                        type="button"
                                        class="nav-link"
                                        role="tab"
                                        id="printing-tab-{{ $printingTabKey }}"
                                        aria-controls="printing-panel-{{ $printingTabKey }}"
                                        :class="{ 'active': printingTab === @js($printingTabKey) }"
                                        :aria-selected="(printingTab === @js($printingTabKey)).toString()"
                                        @click="setPrintingTab(@js($printingTabKey))"
                                    >{{ $printingTabLabel }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <form id="printing-panel-printers" role="tabpanel" aria-labelledby="printing-tab-printers" x-show="printingTab === 'printers'" method="POST" action="{{ route('operations.settings.shop.printing.update') }}">
                        @csrf
                        @method('PATCH')
                        @php
                            $labelOrientation = old('qz_key_tag_orientation', $settings->qz_key_tag_orientation ?? config('printing.key_tag_qz_orientation', 'portrait'));
                            $labelMedia = old('qz_key_tag_media_type', $settings->qz_key_tag_media_type ?? 'mono');
                            $keyTagVinDisplay = old('qz_key_tag_vin_display', $settings->qz_key_tag_vin_display);
                            $keyTagMileage = old('key_tag_mileage_requirement', $settings->key_tag_mileage_requirement ?? 'none');
                            $rasterDpi = old('qz_raster_dpi', $settings->qz_raster_dpi);
                        @endphp
                        <div class="card-body">
                            <label class="form-check form-switch mb-3">
                                <input type="hidden" name="qz_printing_enabled" value="0">
                                <input type="checkbox" name="qz_printing_enabled" value="1" @checked(old('qz_printing_enabled', $settings->qz_printing_enabled)) class="form-check-input">
                                <span class="form-check-label">Enable QZ label printing on repair orders</span>
                            </label>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="settings-qz-key-tag-printer" class="form-label">Key tag printer</label>
                                    <input id="settings-qz-key-tag-printer" type="text" name="qz_printing_key_tag_printer" value="{{ old('qz_printing_key_tag_printer', $settings->qz_printing_key_tag_printer) }}" placeholder="Brother QL-800" @class(['form-control', 'is-invalid' => $errors->has('qz_printing_key_tag_printer')])>
                                    @error('qz_printing_key_tag_printer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-qz-oil-sticker-printer" class="form-label">Oil sticker printer</label>
                                    <input id="settings-qz-oil-sticker-printer" type="text" name="qz_printing_oil_sticker_printer" value="{{ old('qz_printing_oil_sticker_printer', $settings->qz_printing_oil_sticker_printer) }}" placeholder="Same as key tag when empty" @class(['form-control', 'is-invalid' => $errors->has('qz_printing_oil_sticker_printer')])>
                                    @error('qz_printing_oil_sticker_printer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="settings-qz-label-width" class="form-label">Label width (mm)</label>
                                    <input id="settings-qz-label-width" type="number" step="0.1" min="10" max="200" name="qz_key_tag_label_width_mm" value="{{ old('qz_key_tag_label_width_mm', $settings->qz_key_tag_label_width_mm) }}" placeholder="62" @class(['form-control', 'is-invalid' => $errors->has('qz_key_tag_label_width_mm')])>
                                    @error('qz_key_tag_label_width_mm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="settings-qz-label-height" class="form-label">Label height (mm)</label>
                                    <input id="settings-qz-label-height" type="number" step="0.1" min="10" max="200" name="qz_key_tag_label_height_mm" value="{{ old('qz_key_tag_label_height_mm', $settings->qz_key_tag_label_height_mm) }}" placeholder="38.1" @class(['form-control', 'is-invalid' => $errors->has('qz_key_tag_label_height_mm')])>
                                    @error('qz_key_tag_label_height_mm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-qz-orientation" class="form-label">Label orientation</label>
                                    <select id="settings-qz-orientation" name="qz_key_tag_orientation" class="form-select">
                                        <option value="portrait" @selected($labelOrientation === 'portrait')>Portrait (QL-800 default)</option>
                                        <option value="landscape" @selected($labelOrientation === 'landscape')>Landscape</option>
                                        <option value="auto" @selected($labelOrientation === 'auto')>Auto (wide label → portrait)</option>
                                    </select>
                                    <div class="form-hint">Use Portrait for Brother QL-800 62×38.1 mm key tags and oil stickers.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-qz-media" class="form-label">Label media</label>
                                    <select id="settings-qz-media" name="qz_key_tag_media_type" class="form-select">
                                        <option value="mono" @selected($labelMedia === 'mono')>Monochrome</option>
                                        <option value="red_black" @selected($labelMedia === 'red_black')>Red / black (DK)</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-qz-vin-display" class="form-label">Key tag VIN display</label>
                                    <select id="settings-qz-vin-display" name="qz_key_tag_vin_display" class="form-select">
                                        <option value="last6" @selected($keyTagVinDisplay === 'last6')>Last 6</option>
                                        <option value="last8" @selected($keyTagVinDisplay === 'last8')>Last 8</option>
                                        <option value="full" @selected($keyTagVinDisplay === 'full')>Full VIN</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-key-tag-mileage" class="form-label">Key tag mileage required</label>
                                    <select id="settings-key-tag-mileage" name="key_tag_mileage_requirement" class="form-select">
                                        <option value="none" @selected($keyTagMileage === 'none')>None - print without mileage in</option>
                                        <option value="in" @selected($keyTagMileage === 'in')>Mileage in - don't print until it is entered</option>
                                    </select>
                                    <div class="form-hint">A blocked print says mileage in is missing. It does not print a blank tag.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-qz-raster-dpi" class="form-label">Raster DPI override</label>
                                    <select id="settings-qz-raster-dpi" name="qz_raster_dpi" class="form-select">
                                        <option value="" @selected($rasterDpi === null)>Auto</option>
                                        <option value="203" @selected((int) $rasterDpi === 203)>203</option>
                                        <option value="300" @selected((int) $rasterDpi === 300)>300</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="settings-oil-interval" class="form-label">Oil change interval (miles)</label>
                                    <input id="settings-oil-interval" type="number" min="1000" max="50000" name="oil_change_interval_miles" value="{{ old('oil_change_interval_miles', $settings->oil_change_interval_miles ?? 5000) }}" @class(['form-control', 'is-invalid' => $errors->has('oil_change_interval_miles')])>
                                    @error('oil_change_interval_miles')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="settings-oil-due-months" class="form-label">Oil due months</label>
                                    <input id="settings-oil-due-months" type="number" min="1" max="24" name="oil_change_sticker_next_due_months" value="{{ old('oil_change_sticker_next_due_months', $settings->oil_change_sticker_next_due_months ?? 6) }}" @class(['form-control', 'is-invalid' => $errors->has('oil_change_sticker_next_due_months')])>
                                    @error('oil_change_sticker_next_due_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">Save Printing</button>
                        </div>
                    </form>

                    <div class="card-body" id="printing-panel-certificates" role="tabpanel" aria-labelledby="printing-tab-certificates" x-show="printingTab === 'certificates'" x-cloak>
                        @include('operations.settings.partials.printing-qz-certificates', ['qzPrintingReference' => $qzPrintingReference ?? []])
                    </div>
                </section>
                <section class="card" x-show="active === 'financial'" x-cloak data-settings-section="financial">
                    <div class="card-header">
                        <div>
                            <div class="subheader">Financial Rules</div>
                            <h2 class="card-title">How estimates are priced</h2>
                            <div class="card-subtitle">These rules flow into line pricing, tax, shop supplies, totals, and estimate snapshots.</div>
                        </div>
                    </div>
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist" aria-label="Financial Rules">
                            @foreach ([
                                'labor' => 'Labor',
                                'labor-policies' => 'Labor Policies',
                                'tax' => 'Tax Rules',
                                'shop-fees' => 'Shop Fees',
                                'deposits' => 'Deposits',
                                'parts' => 'Parts Matrix',
                                'billing-classes' => 'Billing Classes',
                            ] as $financialTabKey => $financialTabLabel)
                                @php
                                    $financialTabActive = $financialTabKey === 'billing-classes'
                                        ? "['billing-classes', 'customer-tags', 'customer-types'].includes(financialTab)"
                                        : 'financialTab === '.\Illuminate\Support\Js::from($financialTabKey);
                                @endphp
                                <li class="nav-item" role="presentation">
                                    <button
                                        type="button"
                                        class="nav-link"
                                        role="tab"
                                        id="financial-tab-{{ $financialTabKey }}"
                                        aria-controls="financial-panel-{{ $financialTabKey }}"
                                        :class="{ 'active': {{ $financialTabActive }} }"
                                        :aria-selected="({{ $financialTabActive }}).toString()"
                                        @click="setFinancialTab(@js($financialTabKey))"
                                    >{{ $financialTabLabel }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="card-body" id="financial-panel-labor" role="tabpanel" aria-labelledby="financial-tab-labor" x-show="financialTab === 'labor'">
                        <form method="POST" action="{{ route('operations.settings.shop.labor.update') }}" data-settings-labor>
                            @csrf
                            @method('PATCH')
                            @php
                                $defaultLaborCategoryKey = old(
                                    'default_labor_category_key',
                                    collect($settings->laborCategories())->firstWhere('is_default', true)['key'] ?? 'mechanical',
                                );
                            @endphp
                            <h3 class="mb-1">Labor categories</h3>
                            <p class="text-secondary small">Each category sets the rate, minimum hours, and rounding for labor lines on the worksheet. The <strong>Default</strong> radio is used for new labor lines (and Operation Class when no operation is picked); its <strong>Rate / hr</strong> is the shop default labor rate. Rounding always rounds <strong>up</strong> to the increment - never down. Set <strong>None</strong> to skip increment rounding. RepairPal and Warranty default with Adjust labor off.</p>
                            @error('labor_categories')
                                <div class="alert alert-danger" role="alert">{{ $message }}</div>
                            @enderror
                            <div class="row g-2 px-3 mb-1 d-none d-xl-flex subheader" aria-hidden="true">
                                <div class="col-xl-2">Category</div>
                                <div class="col-xl-2">Rate / hr</div>
                                <div class="col-xl-1">Min hrs</div>
                                <div class="col-xl-4">Round to</div>
                                <div class="col-xl-2">Adjust labor</div>
                                <div class="col-xl-1">Default</div>
                            </div>
                            <div class="list-group">
                                @foreach ($settings->laborCategories() as $index => $category)
                                    @php
                                        $allowsModifiers = (string) old('labor_categories.'.$index.'.allows_modifiers', $category['allows_modifiers'] ? '1' : '0') === '1';
                                        $laborField = 'labor-category-'.$index;
                                    @endphp
                                    <div class="list-group-item" data-labor-category="{{ $category['key'] }}">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-12 col-xl-2">
                                                <label for="{{ $laborField }}-name" class="form-label d-xl-none">Category</label>
                                                <input type="hidden" name="labor_categories[{{ $index }}][key]" value="{{ $category['key'] }}">
                                                <input id="{{ $laborField }}-name" name="labor_categories[{{ $index }}][name]" value="{{ old('labor_categories.'.$index.'.name', $category['name']) }}" required maxlength="64" aria-label="Category name" class="form-control fw-medium">
                                            </div>
                                            <div class="col-6 col-xl-2">
                                                <label for="{{ $laborField }}-rate" class="form-label d-xl-none">Rate / hr</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input id="{{ $laborField }}-rate" name="labor_categories[{{ $index }}][rate]" value="{{ old('labor_categories.'.$index.'.rate', number_format($category['rate_cents'] / 100, 2, '.', '')) }}" required inputmode="decimal" aria-label="Rate per hour for {{ $category['name'] }}" class="form-control font-monospace">
                                                </div>
                                            </div>
                                            <div class="col-6 col-xl-1">
                                                <label for="{{ $laborField }}-minimum" class="form-label d-xl-none">Min hrs</label>
                                                <input id="{{ $laborField }}-minimum" name="labor_categories[{{ $index }}][minimum_hours]" value="{{ old('labor_categories.'.$index.'.minimum_hours', $category['minimum_hours']) }}" required inputmode="decimal" aria-label="Minimum hours for {{ $category['name'] }}" class="form-control font-monospace px-2">
                                            </div>
                                            <div class="col-12 col-xl-4">
                                                <div class="form-label d-xl-none">Round to</div>
                                                <div class="btn-group w-100" role="radiogroup" aria-label="Round billable hours for {{ $category['name'] }}">
                                                    @foreach (['exact' => 'None', 'tenth' => '0.1', 'quarter' => '0.25', 'half' => '0.5'] as $ruleKey => $ruleLabel)
                                                        <input
                                                            type="radio"
                                                            id="{{ $laborField }}-rounding-{{ $ruleKey }}"
                                                            name="labor_categories[{{ $index }}][rounding_rule]"
                                                            value="{{ $ruleKey }}"
                                                            @checked(old('labor_categories.'.$index.'.rounding_rule', $category['rounding_rule']) === $ruleKey)
                                                            class="btn-check"
                                                        >
                                                        <label for="{{ $laborField }}-rounding-{{ $ruleKey }}" class="btn btn-sm btn-outline-primary">{{ $ruleLabel }}@if ($ruleKey !== 'exact')<span class="ms-1 small">hr</span>@endif</label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="col-8 col-xl-2">
                                                <input type="hidden" name="labor_categories[{{ $index }}][allows_modifiers]" value="0">
                                                <label class="form-check mb-0">
                                                    <input
                                                        type="checkbox"
                                                        name="labor_categories[{{ $index }}][allows_modifiers]"
                                                        value="1"
                                                        @checked($allowsModifiers)
                                                        class="form-check-input"
                                                    >
                                                    <span class="form-check-label">Allow modifiers</span>
                                                    <span class="form-check-description">Difficulty, hour override, manual rate on worksheet.</span>
                                                </label>
                                            </div>
                                            <div class="col-4 col-xl-1">
                                                <label class="form-check mb-0">
                                                    <input type="radio" name="default_labor_category_key" value="{{ $category['key'] }}" @checked($defaultLaborCategoryKey === $category['key']) class="form-check-input">
                                                    <span class="form-check-label">Default</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="d-flex justify-content-end border-top pt-3 mt-3">
                                <button type="submit" class="btn btn-primary">Save Labor</button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body" id="financial-panel-labor-policies" role="tabpanel" aria-labelledby="financial-tab-labor-policies" x-show="financialTab === 'labor-policies'" x-cloak>
                        @include('operations.settings.partials.labor-policies')
                    </div>

                    <div class="card-body" id="financial-panel-tax" role="tabpanel" aria-labelledby="financial-tab-tax" x-show="financialTab === 'tax'" x-cloak>
                        @php
                            $taxEnabled = (bool) old('tax_enabled', $settings->tax_enabled);
                        @endphp
                        <form method="POST" action="{{ route('operations.settings.shop.tax.update') }}" x-data="{ taxEnabled: @js($taxEnabled) }" style="max-width: 48rem" data-settings-tax>
                            @csrf
                            @method('PATCH')
                            <h3 class="mb-1">Tax outcome</h3>
                            <p class="text-secondary small">Used when calculating taxable estimate/invoice totals. Set rate, label, and which sell amounts tax applies to - including allocated shop fees when enabled.</p>

                            <label class="form-check form-switch">
                                <input type="hidden" name="tax_enabled" value="0">
                                <input type="checkbox" name="tax_enabled" value="1" x-model="taxEnabled" @checked($taxEnabled) class="form-check-input">
                                <span class="form-check-label">Charge sales tax</span>
                            </label>
                            <p x-show="! taxEnabled" @if ($taxEnabled) x-cloak @endif class="text-secondary small">Tax is off. Estimates and invoices show no sales tax. Turn it on to set the rate and what it applies to.</p>
                            <div x-show="taxEnabled" @unless ($taxEnabled) x-cloak @endunless class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="settings-tax-label" class="form-label">Tax display name</label>
                                    <input id="settings-tax-label" name="tax_label" value="{{ old('tax_label', $settings->taxLabel()) }}" required maxlength="64" placeholder="Tax" @class(['form-control', 'is-invalid' => $errors->has('tax_label')])>
                                    @error('tax_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="settings-tax-rate" class="form-label">Sales tax rate</label>
                                    <div @class(['input-group', 'has-validation' => $errors->has('default_tax_rate')])>
                                        <input id="settings-tax-rate" name="default_tax_rate" value="{{ old('default_tax_rate', $settings->salesTaxRate()) }}" required inputmode="decimal" @class(['form-control', 'is-invalid' => $errors->has('default_tax_rate')])>
                                        <span class="input-group-text">%</span>
                                        @error('default_tax_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <fieldset class="col-12">
                                    <legend class="form-label">Apply tax to</legend>
                                    <div class="d-flex flex-wrap gap-4">
                                        <label class="form-check mb-0">
                                            <input type="hidden" name="taxable_labor" value="0">
                                            <input type="checkbox" name="taxable_labor" value="1" @checked((bool) old('taxable_labor', $settings->taxable_labor)) class="form-check-input">
                                            <span class="form-check-label">Tax labor</span>
                                        </label>
                                        <label class="form-check mb-0">
                                            <input type="hidden" name="taxable_parts" value="0">
                                            <input type="checkbox" name="taxable_parts" value="1" @checked((bool) old('taxable_parts', $settings->taxable_parts)) class="form-check-input">
                                            <span class="form-check-label">Tax parts</span>
                                        </label>
                                        <label class="form-check mb-0">
                                            <input type="hidden" name="taxable_shop_fees" value="0">
                                            <input type="checkbox" name="taxable_shop_fees" value="1" @checked((bool) old('taxable_shop_fees', $settings->taxable_shop_fees)) class="form-check-input">
                                            <span class="form-check-label">Tax shop fees</span>
                                        </label>
                                    </div>
                                </fieldset>
                            </div>
                            <div class="border rounded px-3 py-2 mt-3 small text-secondary" data-settings-tax-saved>
                                Saved tax setup: {{ $settings->tax_enabled ? $settings->salesTaxRate().' '.$settings->taxLabel() : 'Tax disabled' }} · {{ $settings->taxable_labor ? 'labor taxable' : 'labor not taxable' }} · {{ $settings->taxable_parts ? 'parts taxable' : 'parts not taxable' }} · {{ $settings->taxable_shop_fees ? 'shop fees taxable' : 'shop fees not taxable' }}.
                            </div>
                            <div class="d-flex justify-content-end border-top pt-3 mt-3">
                                <button type="submit" class="btn btn-primary">Save Tax Rules</button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body" id="financial-panel-shop-fees" role="tabpanel" aria-labelledby="financial-tab-shop-fees" x-show="financialTab === 'shop-fees'" x-cloak>
                        @php
                            $shopFeeEnabled = (bool) old('shop_fee_enabled', $settings->shop_fee_enabled);
                        @endphp
                        <form method="POST" action="{{ route('operations.settings.shop.fees.update') }}" x-data="{ shopFeeEnabled: @js($shopFeeEnabled) }" style="max-width: 48rem" data-settings-fees>
                            @csrf
                            @method('PATCH')
                            <p class="text-secondary small">
                                Shop-wide fee rate and cap for scopes set to <strong>Shop default</strong> or <strong>Customer pay</strong>. Each scope on the estimate has its own Billing setting - billing classes do not turn fees on or off for the whole repair order.
                            </p>
                            <label class="form-check form-switch">
                                <input type="hidden" name="shop_fee_enabled" value="0">
                                <input type="checkbox" name="shop_fee_enabled" value="1" x-model="shopFeeEnabled" @checked($shopFeeEnabled) class="form-check-input">
                                <span class="form-check-label">Charge a shop fee</span>
                            </label>
                            <p x-show="! shopFeeEnabled" @if ($shopFeeEnabled) x-cloak @endif class="text-secondary small">No shop fee is added to estimates. Turn it on to set the rate and cap.</p>
                            <div x-show="shopFeeEnabled" @unless ($shopFeeEnabled) x-cloak @endunless class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="settings-shop-fee-rate" class="form-label">Shop fee rate</label>
                                    <input id="settings-shop-fee-rate" name="shop_fee_rate" value="{{ old('shop_fee_rate', $settings->shop_fee_rate) }}" required inputmode="decimal" @class(['form-control', 'is-invalid' => $errors->has('shop_fee_rate')])>
                                    @error('shop_fee_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="settings-shop-fee-cap" class="form-label">Estimate fee cap</label>
                                    <input id="settings-shop-fee-cap" name="shop_fee_cap" value="{{ old('shop_fee_cap', $settings->shopFeeCap()) }}" inputmode="decimal" placeholder="No cap" @class(['form-control', 'is-invalid' => $errors->has('shop_fee_cap')])>
                                    @error('shop_fee_cap')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="d-flex justify-content-end border-top pt-3 mt-3">
                                <button type="submit" class="btn btn-primary">Save Shop Fees</button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body" id="financial-panel-deposits" role="tabpanel" aria-labelledby="financial-tab-deposits" x-show="financialTab === 'deposits'" x-cloak>
                        @php
                            $selectedDiagnosticKeys = old(
                                'default_deposit_diagnostic_labor_category_keys',
                                $settings->defaultDepositDiagnosticLaborCategoryKeys(),
                            );
                            $depositEnabled = (bool) old('default_deposit_enabled', $settings->defaultDepositEnabled());
                            $depositIncludeDiagnostics = (bool) old('default_deposit_include_diagnostics', $settings->defaultDepositIncludeDiagnostics());
                        @endphp
                        <form method="POST" action="{{ route('operations.settings.shop.deposits.update') }}" x-data="{ depositEnabled: @js($depositEnabled), depositIncludeDiagnostics: @js($depositIncludeDiagnostics) }" style="max-width: 48rem" data-settings-deposits>
                            @csrf
                            @method('PATCH')
                            <p class="text-secondary small">
                                Suggested deposit shown with the repair order financials and used for Square deposit capture. Sums billable estimate parts plus diagnostic labor on Diagnostics scopes only (tax and shop fees included per line).
                            </p>
                            <label class="form-check form-switch">
                                <input type="hidden" name="default_deposit_enabled" value="0">
                                <input type="checkbox" name="default_deposit_enabled" value="1" x-model="depositEnabled" @checked($depositEnabled) class="form-check-input">
                                <span class="form-check-label">Suggest a default deposit</span>
                            </label>
                            <p x-show="! depositEnabled" @if ($depositEnabled) x-cloak @endif class="text-secondary small">No deposit is suggested on repair orders. Turn it on to choose what the deposit includes.</p>
                            <div x-show="depositEnabled" @unless ($depositEnabled) x-cloak @endunless>
                                <fieldset class="mb-3">
                                    <legend class="form-label">Include in the deposit</legend>
                                    <div class="d-flex flex-wrap gap-4">
                                        <label class="form-check mb-0">
                                            <input type="hidden" name="default_deposit_include_parts" value="0">
                                            <input type="checkbox" name="default_deposit_include_parts" value="1" @checked((bool) old('default_deposit_include_parts', $settings->defaultDepositIncludeParts())) class="form-check-input">
                                            <span class="form-check-label">Include parts</span>
                                        </label>
                                        <label class="form-check mb-0">
                                            <input type="hidden" name="default_deposit_include_diagnostics" value="0">
                                            <input type="checkbox" name="default_deposit_include_diagnostics" value="1" x-model="depositIncludeDiagnostics" @checked($depositIncludeDiagnostics) class="form-check-input">
                                            <span class="form-check-label">Include diagnostics</span>
                                        </label>
                                    </div>
                                </fieldset>
                                <fieldset x-show="depositIncludeDiagnostics" @unless ($depositIncludeDiagnostics) x-cloak @endunless class="border rounded p-3">
                                    <legend class="subheader float-none w-auto px-1 mb-1">Diagnostic labor categories</legend>
                                    <p class="text-secondary small">On Diagnostics scopes only, labor in these categories counts toward the default deposit when diagnostics are included.</p>
                                    <div class="row g-2">
                                        @foreach ($settings->laborCategories() as $category)
                                            <div class="col-12 col-sm-6">
                                                <label class="form-check mb-0">
                                                    <input
                                                        type="checkbox"
                                                        name="default_deposit_diagnostic_labor_category_keys[]"
                                                        value="{{ $category['key'] }}"
                                                        @checked(in_array($category['key'], $selectedDiagnosticKeys, true))
                                                        class="form-check-input"
                                                    >
                                                    <span class="form-check-label">{{ $category['name'] }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('default_deposit_diagnostic_labor_category_keys')
                                        <div class="text-danger small mt-2">{{ $message }}</div>
                                    @enderror
                                </fieldset>
                            </div>
                            <div class="d-flex justify-content-end border-top pt-3 mt-3">
                                <button type="submit" class="btn btn-primary">Save Deposit Settings</button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body" id="financial-panel-parts" role="tabpanel" aria-labelledby="financial-tab-parts" x-show="financialTab === 'parts'" x-cloak>
                        @php
                            $partsMatrixCount = count($settings->partsMatrices());
                            $defaultPartsMatrixKey = $settings->defaultPartsMatrix()['key'];
                        @endphp
                        @foreach ($settings->partsMatrices() as $matrix)
                            <form id="delete-parts-matrix-{{ $matrix['key'] }}" method="POST" action="{{ route('operations.settings.shop.parts-matrices.destroy', $matrix['key']) }}" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endforeach
                        <form id="parts-matrix-form" method="POST" action="{{ route('operations.settings.shop.parts-matrices.update', $settings->defaultPartsMatrix()['key']) }}">
                            @csrf
                            @method('PATCH')
                            <div class="row g-2 mb-3">
                                <div class="col-12 col-md-4">
                                    <div class="card card-sm h-100">
                                        <div class="card-body">
                                            <div class="subheader">You set</div>
                                            <div class="h3 mb-0">Markup %</div>
                                            <div class="small text-secondary">Changes sell price policy</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="card card-sm h-100">
                                        <div class="card-body">
                                            <div class="subheader">Calculated</div>
                                            <div class="h3 mb-0">Margin %</div>
                                            <div class="small text-secondary">Parts gross profit from your markup</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="card card-sm h-100">
                                        <div class="card-body">
                                            <div class="subheader">Target</div>
                                            <div class="h3 mb-0">{{ App\Ark\Operations\Settings\ShopSettings::SURVIVABILITY_TARGETS['parts_gp'] }}</div>
                                            <div class="small text-secondary">Parts GP health</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-secondary small">
                                Markup is the editable pricing policy. Margin is calculated visibility for parts GP health. Matrices affect new part sell pricing; line overrides remain operational exceptions, not hidden settings behavior.
                                <a href="{{ route('operations.owner.parts-matrix-tune') }}" class="fw-medium">Matrix tune assistant</a>
                                - simulate tier changes from closed part-line history before saving live policy.
                            </p>
                            @error('parts_matrices')
                                <div class="alert alert-danger" role="alert">{{ $message }}</div>
                            @enderror
                            @foreach ($settings->partsMatrices() as $matrixIndex => $matrix)
                                @php
                                    $canDeletePartsMatrix = $partsMatrixCount > 1 && $matrix['key'] !== $defaultPartsMatrixKey;
                                @endphp
                                <section
                                    class="card card-sm mb-3"
                                    data-parts-matrix="{{ $matrix['key'] }}"
                                    x-data="matrixSection(@js($matrix['rows']), @js($matrix['name']), 'delete-parts-matrix-{{ $matrix['key'] }}')"
                                >
                                    <div class="card-header">
                                        <div class="row g-2 align-items-center flex-fill">
                                            <div class="col">
                                                <input type="hidden" name="parts_matrices[{{ $matrixIndex }}][key]" value="{{ $matrix['key'] }}">
                                                <input data-matrix-name-input name="parts_matrices[{{ $matrixIndex }}][name]" x-model="matrixName" @input="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')" required aria-label="Matrix name" class="form-control fw-medium">
                                            </div>
                                            <div class="col-auto">
                                                <label class="form-check mb-0">
                                                    <input type="radio" name="default_parts_matrix_key" value="{{ $matrix['key'] }}" x-model="defaultPartsMatrixKey" @change="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')" class="form-check-input">
                                                    <span class="form-check-label">Default</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-vcenter card-table mb-0">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Cost from</th>
                                                    <th scope="col">Cost to</th>
                                                    <th scope="col">Markup %</th>
                                                    <th scope="col">Margin</th>
                                                    <th scope="col" class="w-1">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(row, rowIndex) in rows" :key="row.uid">
                                                    <tr @input="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')" @change="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')">
                                                        <td style="min-width: 9rem">
                                                            <input :name="`parts_matrices[{{ $matrixIndex }}][rows][${rowIndex}][min_cost]`" x-model="row.min_cost" required inputmode="decimal" :aria-label="`Cost from, row ${rowIndex + 1}`" class="form-control font-monospace">
                                                        </td>
                                                        <td style="min-width: 9rem">
                                                            <input :name="`parts_matrices[{{ $matrixIndex }}][rows][${rowIndex}][max_cost]`" x-model="row.max_cost" inputmode="decimal" placeholder="No upper limit" :aria-label="`Cost to, row ${rowIndex + 1}`" class="form-control font-monospace">
                                                        </td>
                                                        <td style="min-width: 8rem">
                                                            <input :name="`parts_matrices[{{ $matrixIndex }}][rows][${rowIndex}][markup_percentage]`" x-model="row.markup_percentage" inputmode="decimal" :aria-label="`Markup percent, row ${rowIndex + 1}`" class="form-control font-monospace">
                                                        </td>
                                                        <td class="text-secondary text-nowrap">
                                                            <span x-text="row.margin_percentage ? `${row.margin_percentage}% saved` : 'Save for margin'"></span>
                                                        </td>
                                                        <td>
                                                            <input type="hidden" :name="`parts_matrices[{{ $matrixIndex }}][rows][${rowIndex}][sort_order]`" :value="rowIndex + 1">
                                                            <button type="button" @click="removeRow(rowIndex); autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')" :disabled="rows.length === 1" class="btn btn-sm btn-ghost-danger">
                                                                Remove
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <button type="button" @click="addRow()" class="btn btn-sm">Add Row</button>
                                                @if ($canDeletePartsMatrix)
                                                    <button
                                                        type="button"
                                                        x-show="! deleteConfirming"
                                                        @click="openDeleteConfirm()"
                                                        class="btn btn-sm btn-ghost-secondary"
                                                    >
                                                        Delete matrix…
                                                    </button>
                                                @elseif ($matrix['key'] === $defaultPartsMatrixKey && $partsMatrixCount > 1)
                                                    <span class="small text-secondary">Default matrix</span>
                                                @endif
                                            </div>
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <span id="parts-matrix-status-{{ $matrixIndex }}" class="small text-secondary" role="status">Autosaves edits</span>
                                                <button type="button" @click="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}', true)" class="btn btn-sm">
                                                    <span>Save <span x-text="matrixName"></span></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    @if ($canDeletePartsMatrix)
                                        <div x-show="deleteConfirming" x-cloak class="card-footer bg-danger-lt" data-parts-matrix-delete>
                                            <p class="small mb-2">
                                                Type <strong x-text="deleteMatrixName"></strong> to permanently delete this matrix. Billing classes using it fall back to shop default. Existing part lines keep stored pricing.
                                            </p>
                                            <div class="row g-2 align-items-end">
                                                <div class="col-12 col-sm">
                                                    <label for="parts-matrix-delete-confirm-{{ $matrixIndex }}" class="form-label">Confirm matrix name</label>
                                                    <input
                                                        id="parts-matrix-delete-confirm-{{ $matrixIndex }}"
                                                        x-model="deleteTypedName"
                                                        x-ref="deleteConfirmInput"
                                                        x-init="$watch('deleteConfirming', (open) => { if (open) { $nextTick(() => $refs.deleteConfirmInput?.focus()); } })"
                                                        autocomplete="off"
                                                        spellcheck="false"
                                                        class="form-control"
                                                    >
                                                </div>
                                                <div class="col-12 col-sm-auto d-flex gap-2">
                                                    <button type="button" @click="cancelDeleteConfirm()" class="btn">Cancel</button>
                                                    <button
                                                        type="button"
                                                        x-show="deleteTypedName.trim() !== deleteMatrixName.trim()"
                                                        disabled
                                                        class="btn btn-danger"
                                                    >
                                                        Delete permanently
                                                    </button>
                                                    <button
                                                        type="button"
                                                        x-show="deleteTypedName.trim() === deleteMatrixName.trim()"
                                                        x-cloak
                                                        @click="submitDelete()"
                                                        class="btn btn-danger"
                                                    >
                                                        Delete permanently
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </section>
                            @endforeach
                        </form>
                    </div>

                    <div class="card-body" id="financial-panel-billing-classes" role="tabpanel" aria-labelledby="financial-tab-billing-classes" x-show="financialTab === 'billing-classes' || financialTab === 'customer-tags' || financialTab === 'customer-types'" x-cloak>
                        <form method="POST" action="{{ route('operations.settings.shop.customer-types.update') }}" data-settings-billing-classes>
                            @csrf
                            @method('PATCH')
                            <p class="text-secondary small">
                                Billing classes identify the customer for billing and set the default when a new scope is added. Fees, parts matrix, and <strong>customer document presentation</strong> follow the billing class unless a scope's <strong>Billing</strong> setting on the estimate overrides it.
                            </p>
                            <div class="alert alert-warning small">
                                Named billing profiles: class <strong>Fleet</strong> supplies fee rates for Fleet scopes; class <strong>Warranty</strong> supplies the parts matrix for Warranty scopes. Standing discounts on classes such as <strong>Military</strong> apply to eligible lines on non-warranty scopes. Partner leads (RepairPal) belong under <strong>Referral source</strong> on the customer - not billing class.
                            </div>

                            @php
                                $storedCustomerTypes = $settings->storedCustomerTypeRows();
                                $billingPillBadge = [
                                    'slate' => 'bg-secondary-lt',
                                    'blue' => 'bg-blue-lt',
                                    'green' => 'bg-green-lt',
                                    'amber' => 'bg-yellow-lt',
                                    'orange' => 'bg-orange-lt',
                                    'rose' => 'bg-red-lt',
                                    'violet' => 'bg-purple-lt',
                                ];
                            @endphp
                            @foreach ($settings->customerTypeRows() as $index => $type)
                                @php
                                    $billingProfile = $settings->customerTagBillingProfile($type['name']);
                                    $billingField = 'billing-class-'.$index;
                                @endphp
                                <section class="card card-sm mb-3" data-billing-class="{{ $index }}">
                                    <div class="card-header">
                                        <h4 class="card-title">{{ $type['name'] }}</h4>
                                        <div class="card-actions">
                                            @if ($billingProfile === 'fleet')
                                                <span class="badge bg-blue-lt">Fleet billing profile</span>
                                            @elseif ($billingProfile === 'warranty')
                                                <span class="badge bg-green-lt">Warranty billing profile</span>
                                            @else
                                                <span class="badge bg-secondary-lt">Standard billing class</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3" style="max-width: 36rem">
                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                <label for="{{ $billingField }}-presentation" class="form-label mb-0">Customer document presentation</label>
                                                    <x-tabler.help-tip
                                                        text="Controls how part lines appear on customer estimate PDFs for this billing class. Internal worksheet and purchasing data stay unchanged. A scope's Billing setting on the estimate can override this."
                                                        label="Customer document presentation help for {{ $type['name'] }}"
                                                    />
                                            </div>
                                            <select id="{{ $billingField }}-presentation" name="customer_types[{{ $index }}][document_presentation_profile]" class="form-select">
                                                @foreach (App\Ark\Operations\Parts\CustomerPartPresentationProfile::cases() as $profile)
                                                    <option
                                                        value="{{ $profile->value }}"
                                                        @selected(old('customer_types.'.$index.'.document_presentation_profile', $type['document_presentation_profile'] ?? 'retail') === $profile->value)
                                                    >{{ $profile->label() }} - {{ $profile->helpText() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-4">
                                                <div class="mb-3">
                                                    <div class="d-flex align-items-center gap-1 mb-2">
                                                        <label for="{{ $billingField }}-name" class="form-label mb-0">Billing class name</label>
                                                            <x-tabler.help-tip
                                                                text="Shown on the customer record. Warranty and Fleet names activate billing profiles for matching scope billing settings."
                                                                label="Billing class name help"
                                                            />
                                                    </div>
                                                    <input id="{{ $billingField }}-name" name="customer_types[{{ $index }}][name]" value="{{ old('customer_types.'.$index.'.name', $type['name']) }}" class="form-control">
                                                </div>
                                                <div>
                                                    <div class="d-flex align-items-center gap-1 mb-2">
                                                        <label for="{{ $billingField }}-pill" class="form-label mb-0">Pill color</label>
                                                            <x-tabler.help-tip
                                                                text="Color of this billing class next to the customer name on a repair order."
                                                                label="Pill color help for {{ $type['name'] }}"
                                                            />
                                                    </div>
                                                    @php
                                                        $pillColor = old('customer_types.'.$index.'.pill_color', $type['pill_color'] ?? 'slate');
                                                    @endphp
                                                    <select id="{{ $billingField }}-pill" name="customer_types[{{ $index }}][pill_color]" class="form-select">
                                                        @foreach (\App\Ark\Operations\Settings\BillingClassPillColor::options() as $option)
                                                            <option value="{{ $option['key'] }}" @selected($pillColor === $option['key'])>{{ $option['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                    <span class="badge {{ $billingPillBadge[\App\Ark\Operations\Settings\BillingClassPillColor::resolve($pillColor, $type['name'])] ?? 'bg-secondary-lt' }} mt-2" data-billing-class-pill>{{ $type['name'] }}</span>
                                                </div>
                                            </div>

                                            @php
                                                $shopFeesOn = (bool) old('customer_types.'.$index.'.shop_fees_enabled', $type['shop_fees_enabled']);
                                            @endphp
                                            <div class="col-12 col-lg-8">
                                                @if ($billingProfile === 'fleet')
                                                    <div class="row g-3" x-data="{ shopFeesOn: @js($shopFeesOn) }">
                                                        <div class="col-12 col-sm-6">
                                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                                <label for="{{ $billingField }}-fees" class="form-label mb-0">Fleet shop fees</label>
                                                                    <x-tabler.help-tip
                                                                        text="Used when a scope billing is set to Fleet. Controls whether Fleet scopes collect shop/hazmat fees and the optional rate override."
                                                                        label="Fleet shop fees help"
                                                                    />
                                                            </div>
                                                            <select id="{{ $billingField }}-fees" name="customer_types[{{ $index }}][shop_fees_enabled]" @change="shopFeesOn = $event.target.value === '1'" class="form-select">
                                                                <option value="1" @selected($shopFeesOn)>Use shop rate</option>
                                                                <option value="0" @selected(! $shopFeesOn)>None</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-12 col-sm-6">
                                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                                <label for="{{ $billingField }}-fee-rate" class="form-label mb-0">Fleet fee rate override %</label>
                                                                    <x-tabler.help-tip
                                                                        text="Optional fee percentage for Fleet scopes. Leave blank to use the shop default rate from the Shop Fees tab."
                                                                        label="Fleet fee rate help"
                                                                    />
                                                            </div>
                                                            <input
                                                                id="{{ $billingField }}-fee-rate"
                                                                name="customer_types[{{ $index }}][shop_fee_rate_override]"
                                                                value="{{ old('customer_types.'.$index.'.shop_fee_rate_override', $type['shop_fee_rate_override']) }}"
                                                                inputmode="decimal"
                                                                placeholder="Shop default"
                                                                @disabled(! $shopFeesOn)
                                                                :disabled="! shopFeesOn"
                                                                class="form-control"
                                                            >
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                                <label for="{{ $billingField }}-matrix" class="form-label mb-0">Fleet parts matrix</label>
                                                                    <x-tabler.help-tip
                                                                        text="Default parts matrix for new part lines on scopes set to Fleet billing."
                                                                        label="Fleet parts matrix help"
                                                                    />
                                                            </div>
                                                            <select id="{{ $billingField }}-matrix" name="customer_types[{{ $index }}][default_parts_matrix_key]" class="form-select">
                                                                <option value="">Shop default</option>
                                                                @foreach ($settings->partsMatrices() as $matrix)
                                                                    <option value="{{ $matrix['key'] }}" @selected(old('customer_types.'.$index.'.default_parts_matrix_key', $type['default_parts_matrix_key']) === $matrix['key'])>{{ $matrix['name'] }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                @elseif ($billingProfile === 'warranty')
                                                    <div>
                                                        <div class="d-flex align-items-center gap-1 mb-2">
                                                            <label for="{{ $billingField }}-matrix" class="form-label mb-0">Warranty parts matrix</label>
                                                                <x-tabler.help-tip
                                                                    text="Used when a scope billing is set to Warranty. Warranty scopes never collect shop/hazmat fees."
                                                                    label="Warranty parts matrix help"
                                                                />
                                                        </div>
                                                        <select id="{{ $billingField }}-matrix" name="customer_types[{{ $index }}][default_parts_matrix_key]" class="form-select">
                                                            <option value="">Shop default</option>
                                                            @foreach ($settings->partsMatrices() as $matrix)
                                                                <option value="{{ $matrix['key'] }}" @selected(old('customer_types.'.$index.'.default_parts_matrix_key', $type['default_parts_matrix_key']) === $matrix['key'])>{{ $matrix['name'] }}</option>
                                                            @endforeach
                                                        </select>
                                                        <input type="hidden" name="customer_types[{{ $index }}][shop_fees_enabled]" value="0">
                                                        <input type="hidden" name="customer_types[{{ $index }}][shop_fee_rate_override]" value="">
                                                    </div>
                                                @else
                                                    <div class="row g-3" x-data="{ shopFeesOn: @js($shopFeesOn) }">
                                                        <div class="col-12 col-sm-6">
                                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                                <label for="{{ $billingField }}-fees" class="form-label mb-0">Shop fees</label>
                                                                    <x-tabler.help-tip
                                                                        text="Whether shop fees apply by default to scopes that follow this billing class. Scope billing on the estimate can still override per concern."
                                                                        label="Shop fees help for {{ $type['name'] }}"
                                                                    />
                                                            </div>
                                                            <select id="{{ $billingField }}-fees" name="customer_types[{{ $index }}][shop_fees_enabled]" @change="shopFeesOn = $event.target.value === '1'" class="form-select">
                                                                <option value="1" @selected($shopFeesOn)>Use shop rate</option>
                                                                <option value="0" @selected(! $shopFeesOn)>None</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-12 col-sm-6">
                                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                                <label for="{{ $billingField }}-fee-rate" class="form-label mb-0">Fee rate override %</label>
                                                                    <x-tabler.help-tip
                                                                        text="Optional fee percentage for scopes using this billing class. Leave blank to use the shop default rate from the Shop Fees tab."
                                                                        label="Fee rate override help for {{ $type['name'] }}"
                                                                    />
                                                            </div>
                                                            <input
                                                                id="{{ $billingField }}-fee-rate"
                                                                name="customer_types[{{ $index }}][shop_fee_rate_override]"
                                                                value="{{ old('customer_types.'.$index.'.shop_fee_rate_override', $type['shop_fee_rate_override']) }}"
                                                                inputmode="decimal"
                                                                placeholder="Shop default"
                                                                @disabled(! $shopFeesOn)
                                                                :disabled="! shopFeesOn"
                                                                class="form-control"
                                                            >
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                                <label for="{{ $billingField }}-matrix" class="form-label mb-0">Default parts matrix</label>
                                                                    <x-tabler.help-tip
                                                                        text="Default parts matrix for new part lines on scopes that follow this billing class."
                                                                        label="Default parts matrix help for {{ $type['name'] }}"
                                                                    />
                                                            </div>
                                                            <select id="{{ $billingField }}-matrix" name="customer_types[{{ $index }}][default_parts_matrix_key]" class="form-select">
                                                                <option value="">Shop default</option>
                                                                @foreach ($settings->partsMatrices() as $matrix)
                                                                    <option value="{{ $matrix['key'] }}" @selected(old('customer_types.'.$index.'.default_parts_matrix_key', $type['default_parts_matrix_key']) === $matrix['key'])>{{ $matrix['name'] }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-12 col-sm-6">
                                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                                <label for="{{ $billingField }}-discount-type" class="form-label mb-0">Standing discount</label>
                                                                    <x-tabler.help-tip
                                                                        text="Percent off labor, parts, or both on non-warranty scopes when this billing class is on the customer. Does not change scope billing."
                                                                        label="Standing discount help"
                                                                    />
                                                            </div>
                                                            <select id="{{ $billingField }}-discount-type" name="customer_types[{{ $index }}][discount_type]" class="form-select">
                                                                <option value="none" @selected(old('customer_types.'.$index.'.discount_type', $type['discount_type']) === 'none')>None</option>
                                                                <option value="labor" @selected(old('customer_types.'.$index.'.discount_type', $type['discount_type']) === 'labor')>Labor</option>
                                                                <option value="parts" @selected(old('customer_types.'.$index.'.discount_type', $type['discount_type']) === 'parts')>Parts</option>
                                                                <option value="both" @selected(old('customer_types.'.$index.'.discount_type', $type['discount_type']) === 'both')>Labor &amp; parts</option>
                                                            </select>
                                                        </div>
                                                        @php
                                                            $builtInDiscountAmount = \App\Ark\Operations\Settings\ShopSettings::builtInDiscountAmountFor($type['name']);
                                                            $builtInDiscountLabel = $builtInDiscountAmount !== null ? rtrim(rtrim($builtInDiscountAmount, '0'), '.').'%' : null;
                                                        @endphp
                                                        <div class="col-12 col-sm-6">
                                                            <div class="d-flex align-items-center gap-1 mb-2">
                                                                <label for="{{ $billingField }}-discount-amount" class="form-label mb-0">Discount %</label>
                                                                    <x-tabler.help-tip
                                                                        text="Applied during estimate recalculation. Shop fees and tax use the discounted sell amount."
                                                                        label="Discount percent help"
                                                                    />
                                                            </div>
                                                            <input
                                                                id="{{ $billingField }}-discount-amount"
                                                                name="customer_types[{{ $index }}][discount_amount]"
                                                                value="{{ old('customer_types.'.$index.'.discount_amount', $storedCustomerTypes[$index]['discount_amount'] ?? null) }}"
                                                                inputmode="decimal"
                                                                placeholder="{{ $builtInDiscountLabel !== null ? 'Built-in '.$builtInDiscountLabel : 'No discount' }}"
                                                                class="form-control"
                                                            >
                                                            <div class="form-hint">
                                                                @if ($builtInDiscountLabel !== null)
                                                                    Uses the built-in {{ $builtInDiscountLabel }} when blank. Enter 0 for no discount.
                                                                @else
                                                                    No discount when blank.
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-12 border-top pt-2 small text-secondary">
                                                            @php
                                                                $defaultPosture = App\Ark\Operations\RepairOrders\ConcernBillingPosture::defaultForCustomerTag($type['name']);
                                                            @endphp
                                                            New scopes default to <strong>{{ $defaultPosture->label() }}</strong> billing when this billing class is on the customer.
                                                            Change fees and matrix per scope on the estimate.
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            @endforeach

                            <section class="card card-sm mb-3" data-billing-class-add>
                                <div class="card-body">
                                    <div class="subheader">Add Billing Class</div>
                                    <p class="text-secondary small">Use names Fleet or Warranty to create a billing profile. Other names are standard billing classes.</p>
                                    <div class="row g-2">
                                        <div class="col-12 col-sm-6">
                                            <input :name="`customer_types[${nextCustomerTypeIndex}][name]`" placeholder="Billing class name" aria-label="New billing class name" class="form-control">
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <select :name="`customer_types[${nextCustomerTypeIndex}][pill_color]`" aria-label="New billing class pill color" class="form-select">
                                                @foreach (\App\Ark\Operations\Settings\BillingClassPillColor::options() as $option)
                                                    <option value="{{ $option['key'] }}" @selected($option['key'] === 'slate')>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input type="hidden" :name="`customer_types[${nextCustomerTypeIndex}][shop_fees_enabled]`" value="1">
                                        <input type="hidden" :name="`customer_types[${nextCustomerTypeIndex}][shop_fee_rate_override]`" value="">
                                        <input type="hidden" :name="`customer_types[${nextCustomerTypeIndex}][default_parts_matrix_key]`" value="">
                                        <input type="hidden" :name="`customer_types[${nextCustomerTypeIndex}][document_presentation_profile]`" value="retail">
                                    </div>
                                </div>
                            </section>
                            <div class="d-flex justify-content-end border-top pt-3">
                                <button type="submit" class="btn btn-primary">Save Billing Classes</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="card" x-show="active === 'overhead'" x-cloak data-settings-section="overhead">
                    <div class="card-header">
                        <div>
                            <div class="subheader">Shop Overhead</div>
                            <h2 class="card-title">Shop overhead worksheet</h2>
                            <div class="card-subtitle">Build the shop’s monthly fixed-cost pool and spread it across expected <strong>billed labor hours</strong>. That produces <strong>shop overhead / billed hr</strong>, which feeds each technician’s loaded cost under Staff. Technician wages are entered per tech - not here.</div>
                            <div class="small text-secondary mt-1">
                                Full walkthrough:
                                <x-operations.learn.guide-link role="admin" article="shop-overhead-setup" :label="\App\Ark\Runtime\DemoInstall::isDemo() ? 'Shop overhead and loaded labor cost' : \App\Support\Branding\Branding::learnName().' → Shop overhead and loaded labor cost'" class="btn btn-link p-0 align-baseline small" />
                            </div>
                        </div>
                    </div>

                    @php
                        $activeTechnicianCount = max(1, $staff->filter(
                            fn ($member): bool => $member->isActive() && $member->worksAsTechnician(),
                        )->count());
                    @endphp

                    @include('operations.settings.partials.shop-overhead-calculator', [
                        'technicianCount' => $activeTechnicianCount,
                        'initialState' => $settings->shopOverheadStateArray() ?: null,
                        'saveUrl' => route('operations.settings.shop.overhead.update'),
                    ])
                </section>

                <section class="card" x-show="active === 'operations'" x-cloak data-settings-section="operations">
                    <div class="card-header">
                        <div>
                            <div class="subheader">Appointments &amp; Setup</div>
                            <h2 class="card-title">Appointments, bays, and shop setup</h2>
                            <div class="card-subtitle">Shop-wide options: appointments and bays, station presence, shop profile presets, and importing customers from a spreadsheet.</div>
                        </div>
                    </div>

                    <div class="card-body">
                        <h3 class="mb-1">Appointments</h3>
                        <p class="text-secondary small">Booking rules, scheduling hours, customer request windows, and bays. Also controls Today&apos;s Appointments on Work.</p>

                        @include('operations.settings.partials.appointments-settings', [
                            'settings' => $settings,
                            'scheduleBays' => $scheduleBays ?? collect(),
                        ])
                    </div>

                    <div class="card-body">
                        @include('operations.settings.partials.operational-profile', ['settings' => $settings])
                    </div>

                    <div class="card-body">
                        @include('operations.settings.partials.shop-csv-import')
                    </div>
                </section>

                <div class="ark-legacy bg-white text-dark border rounded p-3" x-show="! @js($tablerSections).includes(active)" x-cloak>

                @include('operations.settings.partials.square-payments-settings', [
                    'settings' => $settings,
                    'platformPaymentsCapture' => $platformPaymentsCapture ?? false,
                ])

                @include('operations.settings.partials.partstech-settings', ['settings' => $settings])

                @include('operations.settings.partials.telephony-settings', [
                    'settings' => $settings,
                    'telephonyHealth' => $telephonyHealth,
                    'telephonyEndpoints' => $telephonyEndpoints,
                    'telephonyEndpointTypes' => $telephonyEndpointTypes,
                    'telephonyExtensions' => $telephonyExtensions,
                    'telephonyExtensionDeviceTypes' => $telephonyExtensionDeviceTypes,
                    'staff' => $staff,
                    'platformMailSend' => $platformMailSend ?? false,
                    'platformVoiceManaged' => $platformVoiceManaged ?? \App\Ark\Platform\Voice\ManagedVoiceGate::platformVoiceReady(),
                ])

                @unless (\App\Ark\Runtime\DemoInstall::isDemo())
                    @include('operations.settings.partials.ark-platform-settings', ['settings' => $settings])
                @endunless

                @include('operations.settings.partials.shop-excellence-targets')

                @can(App\Ark\Runtime\Authorization\ArkCapability::StaffManage->value)
                    @include('operations.settings.partials.staff')
                @endcan

                @include('operations.settings.partials.runtime-health', [
                    'telephonyHealth' => $telephonyHealth,
                    'settings' => $settings,
                ])
                </div>
            </div>
        </div>

        <p class="text-end small mt-3 mb-0">
            <a
                href="{{ route('operations.settings.shop.edit', ['section' => 'runtime-health']) }}"
                class="text-secondary"
                @click.prevent="setActive('runtime-health')"
            >Runtime health</a>
        </p>
    </section>
    </x-tabler.page>
</x-operations.app>
