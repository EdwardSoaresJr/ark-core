<x-operations.app title="Shop Settings" ui="tabler">
    <x-tabler.page layout="fluid" title="Shop Settings" pretitle="System" subtitle="How this shop runs: shop details, pricing, documents, workflow, staff, and connected services.">
    <section
        data-shop-settings
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

                // Retired Financial → Billing Classes tab (#180): deep links land on Labor.
                if (fromUrl === 'billing-classes' || fromUrl === 'customer-types' || fromUrl === 'customer-tags' || fromUrl === 'labor-policies') {
                    return 'labor';
                }

                if (['labor', 'tax', 'shop-fees', 'deposits', 'parts'].includes(fromUrl)) {
                    return fromUrl;
                }

                const stored = localStorage.getItem('ark:shop-settings:financial-tab') || 'labor';

                if (stored === 'labor-policies' || stored === 'billing-classes' || stored === 'customer-types' || stored === 'customer-tags') {
                    return 'labor';
                }

                return stored;
            })(),
            openManageBillingClassesOnLoad: (() => {
                const fromUrl = new URLSearchParams(window.location.search).get('financial-tab');
                const explicit = new URLSearchParams(window.location.search).get('manage-billing-classes');

                return explicit === '1'
                    || fromUrl === 'billing-classes'
                    || fromUrl === 'customer-types'
                    || fromUrl === 'customer-tags';
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
            setActive(section) {
                this.active = section;
                localStorage.setItem('ark:shop-settings:section', section);
                const url = new URL(window.location.href);
                url.searchParams.set('section', section);
                window.history.replaceState({}, '', url);
            },
            init() {
                const fromUrl = new URLSearchParams(window.location.search).get('financial-tab');
                if (fromUrl === 'billing-classes' || fromUrl === 'customer-types' || fromUrl === 'customer-tags') {
                    const url = new URL(window.location.href);
                    url.searchParams.set('section', 'financial');
                    url.searchParams.set('financial-tab', 'labor');
                    url.searchParams.set('manage-billing-classes', '1');
                    window.history.replaceState({}, '', url);
                    localStorage.setItem('ark:shop-settings:financial-tab', 'labor');
                }
            },
            setFinancialTab(tab) {
                if (tab === 'billing-classes' || tab === 'customer-types' || tab === 'customer-tags') {
                    this.financialTab = 'labor';
                    this.openManageBillingClassesOnLoad = true;
                    localStorage.setItem('ark:shop-settings:financial-tab', 'labor');
                    const url = new URL(window.location.href);
                    url.searchParams.set('financial-tab', 'labor');
                    url.searchParams.set('manage-billing-classes', '1');
                    window.history.replaceState({}, '', url);
                    window.dispatchEvent(new CustomEvent('labor-open-manage-billing-classes'));
                    return;
                }
                this.financialTab = tab;
                localStorage.setItem('ark:shop-settings:financial-tab', tab);
                const url = new URL(window.location.href);
                url.searchParams.set('financial-tab', tab);
                url.searchParams.delete('manage-billing-classes');
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
        <p class="text-secondary small">Labor rates and the Parts Matrix save as you edit. Tax, Shop Fees, Deposits, and Class Settings use their own Save buttons.</p>

        <div class="row g-3">
            @include('operations.settings.partials.section-nav')

            <div class="col-12 col-lg-9 col-xxl-10">
                <section class="card" x-show="active === 'general'" data-settings-section="general">
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
                <section class="card" x-show="active === 'estimates'" x-cloak data-settings-section="estimates">
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
                                'tax' => 'Tax Rules',
                                'shop-fees' => 'Shop Fees',
                                'deposits' => 'Deposits',
                                'parts' => 'Parts Matrix',
                            ] as $financialTabKey => $financialTabLabel)
                                <li class="nav-item" role="presentation">
                                    <button
                                        type="button"
                                        class="nav-link"
                                        role="tab"
                                        id="financial-tab-{{ $financialTabKey }}"
                                        aria-controls="financial-panel-{{ $financialTabKey }}"
                                        :class="{ 'active': financialTab === @js($financialTabKey) }"
                                        :aria-selected="(financialTab === @js($financialTabKey)).toString()"
                                        @click="setFinancialTab(@js($financialTabKey))"
                                    >{{ $financialTabLabel }}</button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @include('operations.settings.partials.financial-master-detail-styles')

                    <div class="card-body" id="financial-panel-labor" role="tabpanel" aria-labelledby="financial-tab-labor" x-show="financialTab === 'labor'" data-settings-labor>
                        @include('operations.settings.partials.labor-work-type-matrix')
                    </div>

                    <div class="card-body" id="financial-panel-tax" role="tabpanel" aria-labelledby="financial-tab-tax" x-show="financialTab === 'tax'" x-cloak>
                        @include('operations.settings.partials.tax-profiles-panel')
                    </div>

                    <div class="card-body" id="financial-panel-shop-fees" role="tabpanel" aria-labelledby="financial-tab-shop-fees" x-show="financialTab === 'shop-fees'" x-cloak>
                        @php
                            $shopFeeEnabled = (bool) old('shop_fee_enabled', $settings->shop_fee_enabled);
                            $shopFeeMode = old('shop_fee_mode', $settings->shopFeeMode());
                        @endphp
                        <form
                            method="POST"
                            action="{{ route('operations.settings.shop.fees.update') }}"
                            x-data="{ shopFeeEnabled: @js($shopFeeEnabled), shopFeeMode: @js($shopFeeMode) }"
                            style="max-width: 48rem"
                            data-settings-fees
                        >
                            @csrf
                            @method('PATCH')
                            <p class="text-secondary small">
                                Applies to new repair orders only. Existing open ROs keep the fee rules they were created with.
                                Billing on each scope still controls whether that work is fee-eligible.
                            </p>
                            <label class="form-check form-switch">
                                <input type="hidden" name="shop_fee_enabled" value="0">
                                <input type="checkbox" name="shop_fee_enabled" value="1" x-model="shopFeeEnabled" @checked($shopFeeEnabled) class="form-check-input">
                                <span class="form-check-label">Charge a shop fee</span>
                            </label>
                            <p x-show="! shopFeeEnabled" @if ($shopFeeEnabled) x-cloak @endif class="text-secondary small">No shop fee is added to estimates. Turn it on to choose percentage, flat per RO, or flat per concern.</p>
                            <div x-show="shopFeeEnabled" @unless ($shopFeeEnabled) x-cloak @endunless class="vstack gap-3">
                                <fieldset class="mb-0">
                                    <legend class="form-label mb-2">Fee type</legend>
                                    <div class="d-flex flex-wrap gap-3">
                                        <label class="form-check">
                                            <input type="radio" class="form-check-input" name="shop_fee_mode" value="percent" x-model="shopFeeMode" @checked($shopFeeMode === 'percent')>
                                            <span class="form-check-label">Percentage of eligible charges</span>
                                        </label>
                                        <label class="form-check">
                                            <input type="radio" class="form-check-input" name="shop_fee_mode" value="flat" x-model="shopFeeMode" @checked($shopFeeMode === 'flat')>
                                            <span class="form-check-label">Fixed dollar fee per RO</span>
                                        </label>
                                        <label class="form-check">
                                            <input type="radio" class="form-check-input" name="shop_fee_mode" value="per_concern" x-model="shopFeeMode" @checked($shopFeeMode === 'per_concern')>
                                            <span class="form-check-label">Fixed dollar fee per concern</span>
                                        </label>
                                    </div>
                                    @error('shop_fee_mode')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </fieldset>
                                <div class="row g-3">
                                    <div class="col-12 col-md-4" x-show="shopFeeMode === 'percent'" @if ($shopFeeMode !== 'percent') x-cloak @endif>
                                        <label for="settings-shop-fee-rate" class="form-label">Shop fee percentage</label>
                                        <input id="settings-shop-fee-rate" name="shop_fee_rate" value="{{ old('shop_fee_rate', $settings->shop_fee_rate) }}" inputmode="decimal" :disabled="shopFeeMode !== 'percent'" @disabled($shopFeeMode !== 'percent') @class(['form-control', 'is-invalid' => $errors->has('shop_fee_rate')])>
                                        @error('shop_fee_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12 col-md-4" x-show="shopFeeMode === 'flat' || shopFeeMode === 'per_concern'" @if (! in_array($shopFeeMode, ['flat', 'per_concern'], true)) x-cloak @endif>
                                        <label for="settings-shop-fee-flat" class="form-label">
                                            <span x-show="shopFeeMode === 'flat'" @if ($shopFeeMode !== 'flat') x-cloak @endif>Flat fee per RO ($)</span>
                                            <span x-show="shopFeeMode === 'per_concern'" @if ($shopFeeMode !== 'per_concern') x-cloak @endif>Flat fee per eligible concern ($)</span>
                                        </label>
                                        <input id="settings-shop-fee-flat" name="shop_fee_flat" value="{{ old('shop_fee_flat', $settings->shopFeeFlat()) }}" inputmode="decimal" :disabled="shopFeeMode !== 'flat' && shopFeeMode !== 'per_concern'" @disabled(! in_array($shopFeeMode, ['flat', 'per_concern'], true)) @class(['form-control', 'is-invalid' => $errors->has('shop_fee_flat')])>
                                        @error('shop_fee_flat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-hint" x-show="shopFeeMode === 'flat'" @if ($shopFeeMode !== 'flat') x-cloak @endif>Charged once for the whole repair order. That amount is the maximum.</div>
                                        <div class="form-hint" x-show="shopFeeMode === 'per_concern'" @if ($shopFeeMode !== 'per_concern') x-cloak @endif>One fee per eligible concern, no matter how many labor or parts lines it has.</div>
                                    </div>
                                    <div class="col-12 col-md-4" x-show="shopFeeMode === 'percent' || shopFeeMode === 'per_concern'" @if (! in_array($shopFeeMode, ['percent', 'per_concern'], true)) x-cloak @endif>
                                        <label for="settings-shop-fee-cap" class="form-label">Maximum total shop fee per RO ($)</label>
                                        <input id="settings-shop-fee-cap" name="shop_fee_cap" value="{{ old('shop_fee_cap', $settings->shopFeeCap()) }}" inputmode="decimal" placeholder="No cap" :disabled="shopFeeMode !== 'percent' && shopFeeMode !== 'per_concern'" @disabled(! in_array($shopFeeMode, ['percent', 'per_concern'], true)) @class(['form-control', 'is-invalid' => $errors->has('shop_fee_cap')])>
                                        @error('shop_fee_cap')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-hint">Optional. Caps the total fee for the repair order, not each concern.</div>
                                    </div>
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
                        @include('operations.settings.partials.parts-matrices-panel')
                    </div>

                    {{-- Shared Billing Classes modals for Labor Class Settings / Manage (#180). No separate Financial tab. --}}
                    @include('operations.settings.partials.billing-classes-shared')
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

                @can(App\Ark\Runtime\Authorization\ArkCapability::StaffManage->value)
                    <section class="card" x-show="active === 'staff'" x-cloak data-settings-section="staff">
                        <div class="card-header">
                            <div>
                                <div class="subheader">Staff Access</div>
                                <h2 class="card-title">Team logins and roles</h2>
                                <div class="card-subtitle">Add name, email, and roles - ARK emails a secure setup link so they choose their own password. Select every hat someone wears: admin, advisor, and/or technician.</div>
                            </div>
                        </div>
                        @include('operations.settings.partials.staff')
                    </section>
                @endcan

                <section class="card" x-show="active === 'excellence'" x-cloak data-settings-section="excellence">
                    <div class="card-header">
                        <div>
                            <div class="subheader">Owner Targets &amp; Reporting</div>
                            <h2 class="card-title">Shop performance targets</h2>
                            <div class="card-subtitle">Targets for posted sales, margins, and the owner digest. They set the green and amber hints on the Operational Report and Margin Health.</div>
                        </div>
                    </div>
                    @include('operations.settings.partials.shop-excellence-targets')
                </section>

                <section class="card" x-show="active === 'payments'" x-cloak data-settings-section="payments">
                    @include('operations.settings.partials.square-payments-settings', [
                        'settings' => $settings,
                        'platformPaymentsCapture' => $platformPaymentsCapture ?? false,
                    ])
                </section>

                <section class="card" x-show="active === 'partstech'" x-cloak data-settings-section="partstech">
                    <div class="card-header">
                        <div>
                            <div class="subheader">Parts catalogs</div>
                            <h2 class="card-title">Catalog buttons on the estimate</h2>
                            <div class="card-subtitle">PartsTech, RepairLink, Nexpart, and any other catalog the estimate toolbar opens.</div>
                        </div>
                    </div>
                    @include('operations.settings.partials.partstech-settings', ['settings' => $settings])
                </section>

                <section class="card" x-show="active === 'communications'" x-cloak data-settings-section="communications">
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
                </section>

                @unless (\App\Ark\Runtime\DemoInstall::isDemo())
                    <section class="card" x-show="active === 'ark-cloud'" x-cloak data-settings-section="ark-cloud">
                        <div class="card-header">
                            <div>
                                <div class="subheader">ARK Platform</div>
                                <h2 class="card-title">Managed ARK services</h2>
                                <div class="card-subtitle">Connect this Box to ARK Platform for Email, Texting, Voice, and other managed services. Shop work stays in ARK. Service accounts are managed in ARK Platform.</div>
                            </div>
                        </div>
                        @include('operations.settings.partials.ark-platform-settings', ['settings' => $settings])
                    </section>
                @endunless

                <section class="card" x-show="active === 'runtime-health'" x-cloak data-settings-section="runtime-health">
                    <div class="card-header">
                        <div>
                            <div class="subheader">Troubleshooting</div>
                            <h2 class="card-title">Runtime health</h2>
                            <div class="card-subtitle">
                                Infrastructure may observe local time. ARK records facts in UTC and projects them into the shop timezone at display time.
                                Use this page after deploys or VPS timezone changes - not for daily operations.
                            </div>
                        </div>
                    </div>
                    @include('operations.settings.partials.runtime-health', [
                        'telephonyHealth' => $telephonyHealth,
                        'settings' => $settings,
                    ])
                </section>
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
