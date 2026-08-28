<x-operations.app title="Shop Settings">
    <section
        x-data="{
            active: @js($initialSection) || new URLSearchParams(window.location.search).get('section') || localStorage.getItem('ark:shop-settings:section') || 'general',
            financialTab: (() => {
                const fromUrl = new URLSearchParams(window.location.search).get('financial-tab');

                if (fromUrl === 'billing-classes' || fromUrl === 'customer-types' || fromUrl === 'customer-tags') {
                    return 'billing-classes';
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
        class="ops-shop-settings space-y-3"
    >
        <div class="border border-slate-300 bg-white px-3 py-2">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Operational Settings</p>
                    <p class="mt-0.5 truncate text-xs font-medium text-slate-500">Financial rules, estimate defaults, and document language for live repair order workflow.</p>
                </div>
                @if (session('status'))
                    <p class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900">{{ session('status') }}</p>
                @endif
            </div>
            <p class="mt-2 text-[11px] leading-4 text-slate-500">All shop configuration saves to the database. Parts matrix autosaves on edit; other sections need their save button.</p>
        </div>

        <div class="grid min-w-0 items-start gap-3 lg:grid-cols-[11rem_minmax(0,1fr)] xl:grid-cols-[12.5rem_minmax(0,1fr)]">
            <aside class="min-w-0 border border-slate-300 bg-white p-2 lg:sticky lg:top-6">
                <p class="px-2 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Operational Domains</p>
                <nav class="grid gap-1 text-sm">
                    <button type="button" @click="setActive('general')" :class="active === 'general' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Shop Identity</button>
                    <button type="button" @click="setActive('financial')" :class="active === 'financial' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Financial Rules</button>
                    <button type="button" @click="setActive('payments')" :class="active === 'payments' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Square Payments</button>
                    <button type="button" @click="setActive('partstech')" :class="active === 'partstech' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">PartsTech</button>
                    <button type="button" @click="setActive('communications')" :class="active === 'communications' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Communications</button>
                    <a href="{{ route('operations.shop.communications') }}" class="block px-3 py-2 text-left font-medium text-slate-600 no-underline hover:bg-slate-50 hover:text-slate-950">Stations &amp; Phones</a>
                    <a href="{{ route('website.manage') }}" class="block px-3 py-2 text-left font-medium text-slate-600 no-underline hover:bg-slate-50 hover:text-slate-950">Website</a>
                    @can(App\Ark\Runtime\Authorization\ArkCapability::GrowthAccess->value)
                        <a href="{{ route('growth.opportunities.index') }}" class="block px-3 py-2 text-left font-medium text-slate-600 no-underline hover:bg-slate-50 hover:text-slate-950">Growth</a>
                    @endcan
                    <button type="button" @click="setActive('overhead')" :class="active === 'overhead' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Shop Overhead</button>
                    <button type="button" @click="setActive('excellence')" :class="active === 'excellence' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium leading-snug">Owner Targets &amp; Reporting</button>
                    <button type="button" @click="setActive('estimates')" :class="active === 'estimates' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Documents / Disclaimers</button>
                    <button type="button" @click="setActive('workflow')" :class="active === 'workflow' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Workflow Defaults</button>
                    <button type="button" @click="setActive('operations')" :class="active === 'operations' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Operations</button>
                    <button type="button" @click="setActive('printing')" :class="active === 'printing' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Label Printing</button>
                    @can(App\Ark\Runtime\Authorization\ArkCapability::StaffManage->value)
                        <button type="button" @click="setActive('staff')" :class="active === 'staff' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Staff</button>
                    @endcan
                    <button type="button" @click="setActive('dragon-memory')" :class="active === 'dragon-memory' ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-medium">Dragon Memory</button>
                </nav>
            </aside>

            <div class="min-w-0 w-full border border-slate-300 bg-white p-4">
                <div class="min-w-0 w-full">
                <section x-show="active === 'general'">
                    <div class="border-b border-slate-200 pb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Shop Identity</p>
                        <h2 class="text-base font-black text-slate-950">Estimate-facing shop identity</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Feeds customer documents, PDFs, and operational contact context.</p>
                    </div>
                    <form method="POST" action="{{ route('operations.settings.shop.general.update') }}" enctype="multipart/form-data" class="mt-4">
                        @csrf
                        @method('PATCH')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2 grid gap-4 rounded-md border border-slate-200 bg-slate-50/60 p-4 md:grid-cols-[180px_minmax(0,1fr)]">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estimate logo</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Used on estimate documents. PNG, JPG, or WebP up to 2 MB.</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-[160px_minmax(0,1fr)] sm:items-start">
                                <div class="flex min-h-20 items-center justify-center rounded-md border border-slate-300 bg-white p-3">
                                    @if ($settings->logo_path)
                                        <img src="{{ Storage::disk('public')->url($settings->logo_path) }}" alt="{{ ($settings->shop_name ?: config('app.name')).' logo' }}" class="max-h-16 max-w-full object-contain">
                                    @else
                                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">No logo</span>
                                    @endif
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500">
                                        Upload logo
                                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-950 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800">
                                    </label>
                                    @if ($settings->logo_path)
                                        <label class="mt-3 inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300">
                                            Remove current logo
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <label class="block text-xs font-medium text-slate-500">
                            Shop name
                            <input name="shop_name" value="{{ old('shop_name', $settings->shop_name) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        </label>
                        <label class="block text-xs font-medium text-slate-500">
                            Shop timezone
                            <input
                                name="shop_timezone"
                                value="{{ old('shop_timezone', $settings->shop_timezone) }}"
                                required
                                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                                placeholder="America/Denver"
                            >
                            <span class="mt-1 block text-[11px] leading-4 text-slate-500">All operational timestamps are stored in UTC and displayed in this timezone.</span>
                        </label>
                        <label class="block text-xs font-medium text-slate-500">
                            Phone
                            <input name="phone" value="{{ old('phone', $settings->phone) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        </label>
                        <label class="block text-xs font-medium text-slate-500">
                            Email
                            <input name="email" value="{{ old('email', $settings->email) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        </label>
                        <label class="block text-xs font-medium text-slate-500">
                            Website
                            <input name="website" value="{{ old('website', $settings->website) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        </label>
                        <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                            Address line 1
                            <input name="address_line_1" value="{{ old('address_line_1', $settings->address_line_1) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        </label>
                        <label class="block text-xs font-medium text-slate-500 md:col-span-2">
                            Address line 2
                            <input name="address_line_2" value="{{ old('address_line_2', $settings->address_line_2) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        </label>
                        <label class="block text-xs font-medium text-slate-500">
                            City
                            <input name="city" value="{{ old('city', $settings->city) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-xs font-medium text-slate-500">
                                State
                                <input name="state" value="{{ old('state', $settings->state) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Postal code
                                <input name="postal_code" value="{{ old('postal_code', $settings->postal_code) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end border-t border-slate-200 pt-4">
                        <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                            Save General
                        </button>
                    </div>
                    </form>
                </section>

                <section x-show="active === 'financial'" x-cloak>
                    <div class="border-b border-slate-200 pb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Financial Rules</p>
                        <h2 class="text-base font-black text-slate-950">Estimate pricing authority</h2>
                        <p class="mt-0.5 text-xs text-slate-500">These rules flow into line pricing, tax, shop supplies, totals, and estimate snapshots.</p>
                    </div>

                    <div class="mt-3 grid gap-px border border-slate-300 bg-slate-300 text-sm sm:grid-cols-3 lg:grid-cols-7">
                        <button type="button" @click="setFinancialTab('labor')" :class="financialTab === 'labor' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Labor</button>
                        <button type="button" @click="setFinancialTab('labor-policies')" :class="financialTab === 'labor-policies' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Labor Policies</button>
                        <button type="button" @click="setFinancialTab('tax')" :class="financialTab === 'tax' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Tax Rules</button>
                        <button type="button" @click="setFinancialTab('shop-fees')" :class="financialTab === 'shop-fees' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Shop Fees</button>
                        <button type="button" @click="setFinancialTab('deposits')" :class="financialTab === 'deposits' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Deposits</button>
                        <button type="button" @click="setFinancialTab('parts')" :class="financialTab === 'parts' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Parts Matrix</button>
                        <button type="button" @click="setFinancialTab('billing-classes')" :class="financialTab === 'billing-classes' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Billing Classes</button>
                    </div>

                    <div x-show="financialTab === 'labor'" class="mt-4 w-full">
                        <form method="POST" action="{{ route('operations.settings.shop.labor.update') }}">
                            @csrf
                            @method('PATCH')
                        @php
                            $defaultLaborCategoryKey = old(
                                'default_labor_category_key',
                                collect($settings->laborCategories())->firstWhere('is_default', true)['key'] ?? 'mechanical',
                            );
                        @endphp
                        <label class="block text-xs font-medium text-slate-500">
                            Default labor rate
                            <input name="default_labor_rate" value="{{ old('default_labor_rate', $settings->defaultLaborRate()) }}" required inputmode="decimal" class="mt-1 w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            <span class="mt-1 block text-[11px] leading-4 text-slate-500">Applied to the default category below. Other categories keep their own rates.</span>
                        </label>
                        <div class="mt-4 overflow-hidden rounded-md border border-slate-200 bg-white">
                            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Labor categories</p>
                                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">Category defaults drive labor authority on the worksheet. The <strong class="font-semibold text-slate-700">Default</strong> radio is used for new labor lines (and Operation Class when no operation is picked). Rounding always rounds <strong class="font-semibold text-slate-700">up</strong> to the increment — never down. Set <strong class="font-semibold text-slate-700">None</strong> to skip increment rounding. RepairPal and Warranty default with Adjust labor off.</p>
                            </div>
                            @error('labor_categories')
                                <div class="border-b border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800">{{ $message }}</div>
                            @enderror
                            <div class="ops-shop-labor-categories">
                                <div class="ops-shop-labor-categories__head" aria-hidden="true">
                                    <span>Category</span>
                                    <span>Rate / hr</span>
                                    <span>Min hrs</span>
                                    <span>Round to</span>
                                    <span>Adjust labor</span>
                                    <span>Default</span>
                                </div>
                                @foreach ($settings->laborCategories() as $index => $category)
                                    @php
                                        $allowsModifiers = (string) old('labor_categories.'.$index.'.allows_modifiers', $category['allows_modifiers'] ? '1' : '0') === '1';
                                    @endphp
                                    <div class="ops-shop-labor-category-row">
                                        <div class="ops-shop-labor-category-row__field">
                                            <span class="ops-shop-labor-category-row__label">Category</span>
                                            <input type="hidden" name="labor_categories[{{ $index }}][key]" value="{{ $category['key'] }}">
                                            <input name="labor_categories[{{ $index }}][name]" value="{{ old('labor_categories.'.$index.'.name', $category['name']) }}" required maxlength="64" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-950">
                                        </div>
                                        <div class="ops-shop-labor-category-row__field">
                                            <span class="ops-shop-labor-category-row__label">Rate / hr</span>
                                            <div class="flex rounded-md border border-slate-300 bg-white focus-within:border-slate-500">
                                                <span class="inline-flex items-center rounded-l-md border-r border-slate-200 bg-slate-50 px-2 text-xs text-slate-500">$</span>
                                                <input name="labor_categories[{{ $index }}][rate]" value="{{ old('labor_categories.'.$index.'.rate', number_format($category['rate_cents'] / 100, 2, '.', '')) }}" required inputmode="decimal" class="min-w-0 flex-1 rounded-r-md border-0 px-2 py-2 text-sm tabular-nums text-slate-950 focus:ring-0">
                                            </div>
                                        </div>
                                        <div class="ops-shop-labor-category-row__field">
                                            <span class="ops-shop-labor-category-row__label">Min hrs</span>
                                            <input name="labor_categories[{{ $index }}][minimum_hours]" value="{{ old('labor_categories.'.$index.'.minimum_hours', $category['minimum_hours']) }}" required inputmode="decimal" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm tabular-nums text-slate-950">
                                        </div>
                                        <div class="ops-shop-labor-category-row__field">
                                            <span class="ops-shop-labor-category-row__label">Round to</span>
                                            <div class="ops-labor-rounding" role="radiogroup" aria-label="Round billable hours for {{ $category['name'] }}">
                                                @foreach (['exact' => 'None', 'tenth' => '0.1', 'quarter' => '0.25', 'half' => '0.5'] as $ruleKey => $ruleLabel)
                                                    <label class="ops-labor-rounding__option">
                                                        <input
                                                            type="radio"
                                                            name="labor_categories[{{ $index }}][rounding_rule]"
                                                            value="{{ $ruleKey }}"
                                                            @checked(old('labor_categories.'.$index.'.rounding_rule', $category['rounding_rule']) === $ruleKey)
                                                            class="sr-only"
                                                        >
                                                        <span class="ops-labor-rounding__value">{{ $ruleLabel }}</span>
                                                        @if ($ruleKey !== 'exact')
                                                            <span class="ops-labor-rounding__unit">hr</span>
                                                        @endif
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="ops-shop-labor-category-row__field">
                                            <span class="ops-shop-labor-category-row__label">Adjust labor</span>
                                            <input type="hidden" name="labor_categories[{{ $index }}][allows_modifiers]" value="0">
                                            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                                                <input
                                                    type="checkbox"
                                                    name="labor_categories[{{ $index }}][allows_modifiers]"
                                                    value="1"
                                                    @checked($allowsModifiers)
                                                    class="rounded border-slate-300 text-slate-950 focus:ring-slate-400"
                                                >
                                                <span>Allow modifiers</span>
                                            </label>
                                            <span class="ops-shop-labor-category-row__hint">Difficulty, hour override, manual rate on worksheet.</span>
                                        </div>
                                        <div class="ops-shop-labor-category-row__field">
                                            <span class="ops-shop-labor-category-row__label">Default</span>
                                            <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                                                <input type="radio" name="default_labor_category_key" value="{{ $category['key'] }}" @checked($defaultLaborCategoryKey === $category['key'])>
                                                Default
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end border-t border-slate-200 pt-4">
                            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                                Save Labor
                            </button>
                        </div>
                        </form>
                    </div>

                    <div x-show="financialTab === 'labor-policies'" x-cloak class="mt-4 w-full">
                        @include('operations.settings.partials.labor-policies')
                    </div>

                    <div x-show="financialTab === 'tax'" x-cloak class="mt-4 max-w-3xl">
                        <form method="POST" action="{{ route('operations.settings.shop.tax.update') }}">
                            @csrf
                            @method('PATCH')
                        <div class="border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Tax outcome</p>
                            <p class="mt-0.5 text-xs leading-5 text-slate-600">Used when calculating taxable estimate/invoice totals. Set rate, label, and which sell amounts tax applies to — including allocated shop fees when enabled.</p>
                        </div>

                        <div class="mt-3 grid gap-4 md:grid-cols-2">
                            <label class="block text-xs font-medium text-slate-500">
                                Tax enabled
                                <select name="tax_enabled" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="0" @selected(! old('tax_enabled', $settings->tax_enabled))>No</option>
                                    <option value="1" @selected((bool) old('tax_enabled', $settings->tax_enabled))>Yes</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Tax display name
                                <input name="tax_label" value="{{ old('tax_label', $settings->taxLabel()) }}" required maxlength="64" placeholder="Tax" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400">
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Sales tax rate
                                <div class="mt-1 flex rounded-md border border-slate-300 bg-white focus-within:border-slate-500">
                                    <input name="default_tax_rate" value="{{ old('default_tax_rate', $settings->salesTaxRate()) }}" required inputmode="decimal" class="min-w-0 flex-1 rounded-l-md border-0 px-3 py-2 text-sm text-slate-950 focus:ring-0">
                                    <span class="inline-flex items-center rounded-r-md border-l border-slate-200 bg-slate-50 px-3 text-sm text-slate-500">%</span>
                                </div>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Tax labor
                                <select name="taxable_labor" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="0" @selected(! old('taxable_labor', $settings->taxable_labor))>No</option>
                                    <option value="1" @selected((bool) old('taxable_labor', $settings->taxable_labor))>Yes</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Tax parts
                                <select name="taxable_parts" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="0" @selected(! old('taxable_parts', $settings->taxable_parts))>No</option>
                                    <option value="1" @selected((bool) old('taxable_parts', $settings->taxable_parts))>Yes</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Tax shop fees
                                <select name="taxable_shop_fees" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="0" @selected(! old('taxable_shop_fees', $settings->taxable_shop_fees))>No</option>
                                    <option value="1" @selected((bool) old('taxable_shop_fees', $settings->taxable_shop_fees))>Yes</option>
                                </select>
                            </label>
                            <div class="md:col-span-2 border border-slate-200 bg-white px-3 py-2 text-xs leading-5 text-slate-500">
                                Effective posture: {{ $settings->tax_enabled ? $settings->salesTaxRate().' '.$settings->taxLabel() : 'Tax disabled' }} · {{ $settings->taxable_labor ? 'labor taxable' : 'labor not taxable' }} · {{ $settings->taxable_parts ? 'parts taxable' : 'parts not taxable' }} · {{ $settings->taxable_shop_fees ? 'shop fees taxable' : 'shop fees not taxable' }}.
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end border-t border-slate-200 pt-4">
                            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                                Save Tax Rules
                            </button>
                        </div>
                        </form>
                    </div>

                    <div x-show="financialTab === 'shop-fees'" x-cloak class="mt-4 max-w-3xl space-y-4">
                        <p class="text-xs leading-5 text-slate-500">
                            Shop-wide fee rate and cap for scopes set to <strong class="font-semibold text-slate-700">Shop default</strong> or <strong class="font-semibold text-slate-700">Customer pay</strong>. Each scope on the estimate has its own billing posture — billing classes do not turn fees on or off for the whole repair order.
                        </p>
                        <form method="POST" action="{{ route('operations.settings.shop.fees.update') }}" class="grid gap-4 md:grid-cols-3">
                            @csrf
                            @method('PATCH')
                        <label class="block text-xs font-medium text-slate-500">
                            Shop fee enabled
                            <select name="shop_fee_enabled" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                <option value="0" @selected(! old('shop_fee_enabled', $settings->shop_fee_enabled))>No</option>
                                <option value="1" @selected((bool) old('shop_fee_enabled', $settings->shop_fee_enabled))>Yes</option>
                            </select>
                        </label>
                        <label class="block text-xs font-medium text-slate-500">
                            Shop fee rate
                            <input name="shop_fee_rate" value="{{ old('shop_fee_rate', $settings->shop_fee_rate) }}" required inputmode="decimal" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        </label>
                        <label class="block text-xs font-medium text-slate-500">
                            Estimate fee cap
                            <input name="shop_fee_cap" value="{{ old('shop_fee_cap', $settings->shopFeeCap()) }}" inputmode="decimal" placeholder="No cap" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400">
                        </label>
                        <div class="md:col-span-3 flex justify-end border-t border-slate-200 pt-4">
                            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                                Save Shop Fees
                            </button>
                        </div>
                        </form>
                    </div>

                    <div x-show="financialTab === 'deposits'" x-cloak class="mt-4 max-w-3xl space-y-4">
                        @php
                            $selectedDiagnosticKeys = old(
                                'default_deposit_diagnostic_labor_category_keys',
                                $settings->defaultDepositDiagnosticLaborCategoryKeys(),
                            );
                        @endphp
                        <p class="text-xs leading-5 text-slate-500">
                            Default deposit amount on the repair order financial rail and Square deposit capture. Sums billable estimate parts plus diagnostic labor on Diagnostics scopes only (tax and shop fees included per line).
                        </p>
                        <form method="POST" action="{{ route('operations.settings.shop.deposits.update') }}" class="grid gap-4 md:grid-cols-2">
                            @csrf
                            @method('PATCH')
                            <label class="block text-xs font-medium text-slate-500">
                                Suggest default deposit
                                <select name="default_deposit_enabled" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="0" @selected(! old('default_deposit_enabled', $settings->defaultDepositEnabled()))>No</option>
                                    <option value="1" @selected((bool) old('default_deposit_enabled', $settings->defaultDepositEnabled()))>Yes</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Include parts
                                <select name="default_deposit_include_parts" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="0" @selected(! old('default_deposit_include_parts', $settings->defaultDepositIncludeParts()))>No</option>
                                    <option value="1" @selected((bool) old('default_deposit_include_parts', $settings->defaultDepositIncludeParts()))>Yes</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Include diagnostics
                                <select name="default_deposit_include_diagnostics" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="0" @selected(! old('default_deposit_include_diagnostics', $settings->defaultDepositIncludeDiagnostics()))>No</option>
                                    <option value="1" @selected((bool) old('default_deposit_include_diagnostics', $settings->defaultDepositIncludeDiagnostics()))>Yes</option>
                                </select>
                            </label>
                            <div class="md:col-span-2 border border-slate-200 bg-white px-3 py-3">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Diagnostic labor categories</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">On Diagnostics scopes only, labor in these categories counts toward the default deposit when diagnostics are included.</p>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    @foreach ($settings->laborCategories() as $category)
                                        <label class="flex items-center gap-2 text-sm text-slate-700">
                                            <input
                                                type="checkbox"
                                                name="default_deposit_diagnostic_labor_category_keys[]"
                                                value="{{ $category['key'] }}"
                                                @checked(in_array($category['key'], $selectedDiagnosticKeys, true))
                                                class="rounded border-slate-300 text-slate-950 focus:ring-slate-500"
                                            >
                                            <span>{{ $category['name'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="md:col-span-2 flex justify-end border-t border-slate-200 pt-4">
                                <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                                    Save Deposit Settings
                                </button>
                            </div>
                        </form>
                    </div>

                    <div x-show="financialTab === 'parts'" x-cloak class="mt-4">
                        @php
                            $partsMatrixCount = count($settings->partsMatrices());
                            $defaultPartsMatrixKey = $settings->defaultPartsMatrix()['key'];
                        @endphp
                        @foreach ($settings->partsMatrices() as $matrix)
                            <form id="delete-parts-matrix-{{ $matrix['key'] }}" method="POST" action="{{ route('operations.settings.shop.parts-matrices.destroy', $matrix['key']) }}" class="hidden">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endforeach
                        <form id="parts-matrix-form" method="POST" action="{{ route('operations.settings.shop.parts-matrices.update', $settings->defaultPartsMatrix()['key']) }}">
                            @csrf
                            @method('PATCH')
                        <div class="space-y-4">
                            <div class="grid gap-px border border-slate-300 bg-slate-300 md:grid-cols-3">
                                <div class="bg-white px-3 py-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Editable authority</p>
                                    <p class="mt-0.5 text-sm font-black text-slate-950">Markup %</p>
                                    <p class="text-[11px] text-slate-500">Changes sell price policy</p>
                                </div>
                                <div class="bg-white px-3 py-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Read-only truth</p>
                                    <p class="mt-0.5 text-sm font-black text-slate-950">Margin %</p>
                                    <p class="text-[11px] text-slate-500">Derived GP visibility</p>
                                </div>
                                <div class="bg-white px-3 py-2">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Target posture</p>
                                    <p class="mt-0.5 text-sm font-black text-slate-950">{{ App\Ark\Operations\Settings\ShopSettings::SURVIVABILITY_TARGETS['parts_gp'] }}</p>
                                    <p class="text-[11px] text-slate-500">Parts GP health</p>
                                </div>
                            </div>
                            <div class="border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-500">
                                Markup is the editable pricing policy. Margin is calculated visibility for parts GP health. Matrices affect new part sell pricing; line overrides remain operational exceptions, not hidden settings behavior.
                                <a href="{{ route('operations.owner.parts-matrix-tune') }}" class="ml-1 font-bold text-slate-700 underline">Matrix tune assistant</a>
                                — simulate tier changes from closed part-line history before saving live policy.
                            </div>
                            @error('parts_matrices')
                                <div class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800">{{ $message }}</div>
                            @enderror
                            @foreach ($settings->partsMatrices() as $matrixIndex => $matrix)
                                @php
                                    $canDeletePartsMatrix = $partsMatrixCount > 1 && $matrix['key'] !== $defaultPartsMatrixKey;
                                @endphp
                                <section
                                    class="overflow-hidden rounded-md border border-slate-200"
                                    x-data="matrixSection(@js($matrix['rows']), @js($matrix['name']), 'delete-parts-matrix-{{ $matrix['key'] }}')"
                                >
                                    <div class="grid gap-3 border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400 md:grid-cols-[1fr_1fr_120px]">
                                        <input type="hidden" name="parts_matrices[{{ $matrixIndex }}][key]" value="{{ $matrix['key'] }}">
                                        <input data-matrix-name-input name="parts_matrices[{{ $matrixIndex }}][name]" x-model="matrixName" @input="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')" required class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm normal-case tracking-normal text-slate-950">
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500">
                                            <input type="radio" name="default_parts_matrix_key" value="{{ $matrix['key'] }}" x-model="defaultPartsMatrixKey" @change="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')">
                                            Default
                                        </label>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full border-collapse text-sm">
                                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                <tr>
                                                    <th scope="col" class="border-b border-slate-200 px-3 py-2 text-left">Cost from</th>
                                                    <th scope="col" class="border-b border-slate-200 px-3 py-2 text-left">Cost to</th>
                                                    <th scope="col" class="border-b border-slate-200 px-3 py-2 text-left">Markup %</th>
                                                    <th scope="col" class="border-b border-slate-200 px-3 py-2 text-left text-slate-400">Margin</th>
                                                    <th scope="col" class="w-24 border-b border-slate-200 px-3 py-2 text-left">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 bg-white">
                                                <template x-for="(row, rowIndex) in rows" :key="row.uid">
                                                    <tr @input="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')" @change="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')">
                                                        <td class="px-3 py-2 align-middle">
                                                            <input :name="`parts_matrices[{{ $matrixIndex }}][rows][${rowIndex}][min_cost]`" x-model="row.min_cost" required inputmode="decimal" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                                        </td>
                                                        <td class="px-3 py-2 align-middle">
                                                            <input :name="`parts_matrices[{{ $matrixIndex }}][rows][${rowIndex}][max_cost]`" x-model="row.max_cost" inputmode="decimal" placeholder="No upper limit" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400">
                                                        </td>
                                                        <td class="px-3 py-2 align-middle">
                                                            <input :name="`parts_matrices[{{ $matrixIndex }}][rows][${rowIndex}][markup_percentage]`" x-model="row.markup_percentage" inputmode="decimal" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                                        </td>
                                                        <td class="px-3 py-2 align-middle text-sm text-slate-400">
                                                            <span x-text="row.margin_percentage ? `${row.margin_percentage}% saved` : 'Save for margin'"></span>
                                                        </td>
                                                        <td class="px-3 py-2 align-middle">
                                                            <input type="hidden" :name="`parts_matrices[{{ $matrixIndex }}][rows][${rowIndex}][sort_order]`" :value="rowIndex + 1">
                                                            <button type="button" @click="removeRow(rowIndex); autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}')" :disabled="rows.length === 1" class="rounded-md px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-30">
                                                                Remove
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/50 px-3 py-2">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <button type="button" @click="addRow()" class="min-h-9 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950">
                                                Add Row
                                            </button>
                                            @if ($canDeletePartsMatrix)
                                                <button
                                                    type="button"
                                                    x-show="! deleteConfirming"
                                                    @click="openDeleteConfirm()"
                                                    class="text-xs font-medium text-slate-400 hover:text-slate-600"
                                                >
                                                    Delete matrix…
                                                </button>
                                            @elseif ($matrix['key'] === $defaultPartsMatrixKey && $partsMatrixCount > 1)
                                                <span class="text-[11px] text-slate-400">Default matrix</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3">
                                        <span id="parts-matrix-status-{{ $matrixIndex }}" class="text-xs text-slate-400">Autosaves edits</span>
                                        <button type="button" @click="autosaveMatrix('{{ $matrix['key'] }}', 'parts-matrix-status-{{ $matrixIndex }}', true)" class="min-h-9 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950">
                                            Save <span x-text="matrixName"></span>
                                        </button>
                                        </div>
                                    </div>
                                    @if ($canDeletePartsMatrix)
                                        <div x-show="deleteConfirming" x-cloak class="border-t border-rose-200 bg-rose-50/60 px-3 py-3">
                                            <p class="text-xs leading-5 text-slate-700">
                                                Type <strong class="font-semibold text-slate-950" x-text="deleteMatrixName"></strong> to permanently delete this matrix. Billing classes using it fall back to shop default. Existing part lines keep stored pricing.
                                            </p>
                                            <div class="mt-3 flex flex-wrap items-end gap-3">
                                                <label class="block min-w-[12rem] flex-1 text-xs font-medium text-slate-500">
                                                    Confirm matrix name
                                                    <input
                                                        x-model="deleteTypedName"
                                                        x-ref="deleteConfirmInput"
                                                        x-init="$watch('deleteConfirming', (open) => { if (open) { $nextTick(() => $refs.deleteConfirmInput?.focus()); } })"
                                                        autocomplete="off"
                                                        spellcheck="false"
                                                        class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950"
                                                    >
                                                </label>
                                                <button type="button" @click="cancelDeleteConfirm()" class="min-h-9 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950">
                                                    Cancel
                                                </button>
                                                <button
                                                    type="button"
                                                    x-show="deleteTypedName.trim() !== deleteMatrixName.trim()"
                                                    disabled
                                                    class="ops-parts-matrix-delete-btn"
                                                >
                                                    Delete permanently
                                                </button>
                                                <button
                                                    type="button"
                                                    x-show="deleteTypedName.trim() === deleteMatrixName.trim()"
                                                    x-cloak
                                                    @click="submitDelete()"
                                                    class="ops-parts-matrix-delete-btn is-ready"
                                                >
                                                    Delete permanently
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </section>
                            @endforeach
                        </div>
                        </form>
                    </div>

                    <div x-show="financialTab === 'billing-classes' || financialTab === 'customer-tags' || financialTab === 'customer-types'" x-cloak class="mt-4 max-w-5xl space-y-3">
                        <form method="POST" action="{{ route('operations.settings.shop.customer-types.update') }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                        <p class="text-xs leading-5 text-slate-500">
                            Billing classes identify the customer for billing and set the default when a new scope is added. Fees, parts matrix, and <strong class="font-semibold text-slate-700">customer document presentation</strong> follow the billing class unless a scope's <strong class="font-semibold text-slate-700">Billing posture</strong> overrides it.
                        </p>
                        <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-950">
                            Named billing profiles: class <strong>Fleet</strong> supplies fee rates for Fleet scopes; class <strong>Warranty</strong> supplies the parts matrix for Warranty scopes. Standing discounts on classes such as <strong>Military</strong> apply to eligible lines on non-warranty scopes. Partner leads (RepairPal) belong under <strong>Referral source</strong> on the customer — not billing class.
                        </p>

                        @foreach ($settings->customerTypeRows() as $index => $type)
                            @php
                                $billingProfile = $settings->customerTagBillingProfile($type['name']);
                            @endphp
                            <section class="rounded-md border border-slate-200 bg-white p-4">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    @if ($billingProfile === 'fleet')
                                        <span class="rounded-sm bg-slate-900 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.08em] text-white">Fleet billing profile</span>
                                    @elseif ($billingProfile === 'warranty')
                                        <span class="rounded-sm bg-emerald-900 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.08em] text-white">Warranty billing profile</span>
                                    @else
                                        <span class="rounded-sm bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-600">Standard billing class</span>
                                    @endif
                                </div>
                                <div class="mb-3 max-w-xl">
                                    <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                        Customer document presentation
                                        <x-operations.help-tip
                                            text="Controls how part lines appear on customer estimate PDFs for this billing class. Internal worksheet and purchasing data stay unchanged. Scope billing posture can override per concern."
                                            label="Customer document presentation help for {{ $type['name'] }}"
                                        />
                                    </div>
                                    <select
                                        name="customer_types[{{ $index }}][document_presentation_profile]"
                                        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950"
                                    >
                                        @foreach (App\Ark\Operations\Parts\CustomerPartPresentationProfile::cases() as $profile)
                                            <option
                                                value="{{ $profile->value }}"
                                                @selected(old('customer_types.'.$index.'.document_presentation_profile', $type['document_presentation_profile'] ?? 'retail') === $profile->value)
                                            >{{ $profile->label() }} — {{ $profile->helpText() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grid gap-4 lg:grid-cols-[minmax(180px,1fr)_2fr]">
                                    <div>
                                        <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                            Billing class name
                                            <x-operations.help-tip
                                                text="Shown on the customer record. Warranty and Fleet names activate billing profiles for matching scope billing settings."
                                                label="Billing class name help"
                                            />
                                        </div>
                                        <input name="customer_types[{{ $index }}][name]" value="{{ old('customer_types.'.$index.'.name', $type['name']) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    </div>

                                    @if ($billingProfile === 'fleet')
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                                    Fleet shop fees
                                                    <x-operations.help-tip
                                                        text="Used when a scope billing is set to Fleet. Controls whether Fleet scopes collect shop/hazmat fees and the optional rate override."
                                                        label="Fleet shop fees help"
                                                    />
                                                </div>
                                                <select name="customer_types[{{ $index }}][shop_fees_enabled]" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                                    <option value="1" @selected((bool) old('customer_types.'.$index.'.shop_fees_enabled', $type['shop_fees_enabled']))>Use shop rate</option>
                                                    <option value="0" @selected(! (bool) old('customer_types.'.$index.'.shop_fees_enabled', $type['shop_fees_enabled']))>None</option>
                                                </select>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                                    Fleet fee rate override %
                                                    <x-operations.help-tip
                                                        text="Optional fee percentage for Fleet scopes. Leave blank to use the shop default rate from the Shop Fees tab."
                                                        label="Fleet fee rate help"
                                                    />
                                                </div>
                                                <input
                                                    name="customer_types[{{ $index }}][shop_fee_rate_override]"
                                                    value="{{ old('customer_types.'.$index.'.shop_fee_rate_override', $type['shop_fee_rate_override']) }}"
                                                    inputmode="decimal"
                                                    placeholder="Shop default"
                                                    @disabled(! (bool) old('customer_types.'.$index.'.shop_fees_enabled', $type['shop_fees_enabled']))
                                                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400 disabled:bg-slate-100 disabled:text-slate-400"
                                                >
                                            </div>
                                            <div class="sm:col-span-2">
                                                <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                                    Fleet parts matrix
                                                    <x-operations.help-tip
                                                        text="Default parts matrix for new part lines on scopes set to Fleet billing."
                                                        label="Fleet parts matrix help"
                                                    />
                                                </div>
                                                <select name="customer_types[{{ $index }}][default_parts_matrix_key]" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                                    <option value="">Shop default</option>
                                                    @foreach ($settings->partsMatrices() as $matrix)
                                                        <option value="{{ $matrix['key'] }}" @selected(old('customer_types.'.$index.'.default_parts_matrix_key', $type['default_parts_matrix_key']) === $matrix['key'])>{{ $matrix['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    @elseif ($billingProfile === 'warranty')
                                        <div>
                                            <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                                Warranty parts matrix
                                                <x-operations.help-tip
                                                    text="Used when a scope billing is set to Warranty. Warranty scopes never collect shop/hazmat fees."
                                                    label="Warranty parts matrix help"
                                                />
                                            </div>
                                            <select name="customer_types[{{ $index }}][default_parts_matrix_key]" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                                <option value="">Shop default</option>
                                                @foreach ($settings->partsMatrices() as $matrix)
                                                    <option value="{{ $matrix['key'] }}" @selected(old('customer_types.'.$index.'.default_parts_matrix_key', $type['default_parts_matrix_key']) === $matrix['key'])>{{ $matrix['name'] }}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="customer_types[{{ $index }}][shop_fees_enabled]" value="0">
                                            <input type="hidden" name="customer_types[{{ $index }}][shop_fee_rate_override]" value="">
                                        </div>
                                    @else
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                                    Shop fees
                                                    <x-operations.help-tip
                                                        text="Default shop fee posture for scopes that follow this billing class. Scope billing on the estimate can still override per concern."
                                                        label="Shop fees help for {{ $type['name'] }}"
                                                    />
                                                </div>
                                                <select name="customer_types[{{ $index }}][shop_fees_enabled]" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                                    <option value="1" @selected((bool) old('customer_types.'.$index.'.shop_fees_enabled', $type['shop_fees_enabled']))>Use shop rate</option>
                                                    <option value="0" @selected(! (bool) old('customer_types.'.$index.'.shop_fees_enabled', $type['shop_fees_enabled']))>None</option>
                                                </select>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                                    Fee rate override %
                                                    <x-operations.help-tip
                                                        text="Optional fee percentage for scopes using this billing class. Leave blank to use the shop default rate from the Shop Fees tab."
                                                        label="Fee rate override help for {{ $type['name'] }}"
                                                    />
                                                </div>
                                                <input
                                                    name="customer_types[{{ $index }}][shop_fee_rate_override]"
                                                    value="{{ old('customer_types.'.$index.'.shop_fee_rate_override', $type['shop_fee_rate_override']) }}"
                                                    inputmode="decimal"
                                                    placeholder="Shop default"
                                                    @disabled(! (bool) old('customer_types.'.$index.'.shop_fees_enabled', $type['shop_fees_enabled']))
                                                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400 disabled:bg-slate-100 disabled:text-slate-400"
                                                >
                                            </div>
                                            <div class="sm:col-span-2">
                                                <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                                    Default parts matrix
                                                    <x-operations.help-tip
                                                        text="Default parts matrix for new part lines on scopes that follow this billing class."
                                                        label="Default parts matrix help for {{ $type['name'] }}"
                                                    />
                                                </div>
                                                <select name="customer_types[{{ $index }}][default_parts_matrix_key]" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                                    <option value="">Shop default</option>
                                                    @foreach ($settings->partsMatrices() as $matrix)
                                                        <option value="{{ $matrix['key'] }}" @selected(old('customer_types.'.$index.'.default_parts_matrix_key', $type['default_parts_matrix_key']) === $matrix['key'])>{{ $matrix['name'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                                    Standing discount
                                                    <x-operations.help-tip
                                                        text="Percent off labor, parts, or both on non-warranty scopes when this billing class is on the customer. Does not change scope billing."
                                                        label="Standing discount help"
                                                    />
                                                </div>
                                                <select name="customer_types[{{ $index }}][discount_type]" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                                    <option value="none" @selected(old('customer_types.'.$index.'.discount_type', $type['discount_type']) === 'none')>None</option>
                                                    <option value="labor" @selected(old('customer_types.'.$index.'.discount_type', $type['discount_type']) === 'labor')>Labor</option>
                                                    <option value="parts" @selected(old('customer_types.'.$index.'.discount_type', $type['discount_type']) === 'parts')>Parts</option>
                                                    <option value="both" @selected(old('customer_types.'.$index.'.discount_type', $type['discount_type']) === 'both')>Labor &amp; parts</option>
                                                </select>
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1 text-xs font-medium text-slate-500">
                                                    Discount %
                                                    <x-operations.help-tip
                                                        text="Applied during estimate recalculation. Shop fees and tax use the discounted sell amount."
                                                        label="Discount percent help"
                                                    />
                                                </div>
                                                <input
                                                    name="customer_types[{{ $index }}][discount_amount]"
                                                    value="{{ old('customer_types.'.$index.'.discount_amount', $type['discount_amount']) }}"
                                                    inputmode="decimal"
                                                    placeholder="0.00"
                                                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400"
                                                >
                                            </div>
                                            <div class="sm:col-span-2 border-t border-slate-100 pt-2 text-xs leading-5 text-slate-500">
                                                @php
                                                    $defaultPosture = App\Ark\Operations\RepairOrders\ConcernBillingPosture::defaultForCustomerTag($type['name']);
                                                @endphp
                                                New scopes default to <strong class="font-semibold text-slate-700">{{ $defaultPosture->label() }}</strong> billing when this billing class is on the customer.
                                                Change fees and matrix per scope on the estimate.
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </section>
                        @endforeach

                        <section class="rounded-md border border-dashed border-slate-300 bg-slate-50/60 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Add Billing Class</p>
                            <p class="mt-1 text-xs text-slate-500">Use names Fleet or Warranty to create a billing profile. Other names are standard billing classes.</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <input :name="`customer_types[${nextCustomerTypeIndex}][name]`" placeholder="Billing class name" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950 placeholder:text-slate-400">
                                <input type="hidden" :name="`customer_types[${nextCustomerTypeIndex}][shop_fees_enabled]`" value="1">
                                <input type="hidden" :name="`customer_types[${nextCustomerTypeIndex}][shop_fee_rate_override]`" value="">
                                <input type="hidden" :name="`customer_types[${nextCustomerTypeIndex}][default_parts_matrix_key]`" value="">
                                <input type="hidden" :name="`customer_types[${nextCustomerTypeIndex}][document_presentation_profile]`" value="retail">
                            </div>
                        </section>
                        <div class="flex justify-end border-t border-slate-200 pt-4">
                            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                                Save Billing Classes
                            </button>
                        </div>
                        </form>
                    </div>
                </section>

                @include('operations.settings.partials.square-payments-settings', ['settings' => $settings])

                @include('operations.settings.partials.partstech-settings', ['settings' => $settings])

                @include('operations.settings.partials.telephony-settings', [
                    'settings' => $settings,
                    'telephonyHealth' => $telephonyHealth,
                    'telephonyEndpoints' => $telephonyEndpoints,
                    'telephonyEndpointTypes' => $telephonyEndpointTypes,
                    'telephonyExtensions' => $telephonyExtensions,
                    'telephonyExtensionDeviceTypes' => $telephonyExtensionDeviceTypes,
                    'staff' => $staff,
                ])

                <section x-show="active === 'overhead'" x-cloak>
                    <div class="border-b border-slate-200 pb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Shop Overhead</p>
                        <h2 class="text-base font-black text-slate-950">Shop overhead worksheet</h2>
                        <p class="mt-0.5 text-xs leading-5 text-slate-500">Build the shop’s monthly fixed-cost pool and spread it across expected <strong class="font-semibold text-slate-700">billed labor hours</strong>. That produces <strong class="font-semibold text-slate-700">shop overhead / billed hr</strong>, which feeds each technician’s loaded cost under Staff. Technician wages are entered per tech — not here.</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Full walkthrough:
                            <x-operations.learn.guide-link role="admin" article="shop-overhead-setup" :label="\App\Support\Branding\Branding::learnName().' → Shop overhead and loaded labor cost'" class="font-semibold text-slate-700 decoration-slate-300 hover:text-slate-950" />
                        </p>
                    </div>

                    @php
                        $activeTechnicianCount = max(1, $staff->filter(
                            fn ($member): bool => $member->isActive() && $member->worksAsTechnician(),
                        )->count());
                    @endphp

                    <div class="mt-4 w-full">
                        @include('operations.settings.partials.shop-overhead-calculator', [
                            'technicianCount' => $activeTechnicianCount,
                            'initialState' => $settings->shopOverheadStateArray() ?: null,
                            'saveUrl' => route('operations.settings.shop.overhead.update'),
                        ])
                    </div>
                </section>

                @include('operations.settings.partials.shop-excellence-targets')

                <section x-show="active === 'estimates'" x-cloak>
                    <div class="border-b border-slate-200 pb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Documents / Disclaimers</p>
                        <h2 class="text-base font-black text-slate-950">Customer-facing document language</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Global and customer-tag disclaimers copy into snapshots at generation. Invoices keep the language issued at that time.</p>
                    </div>
                    <form method="POST" action="{{ route('operations.settings.shop.estimates.update') }}" class="mt-4">
                        @csrf
                        @method('PATCH')
                    <div class="grid gap-5">
                        <div class="grid gap-px border border-slate-300 bg-slate-300 md:grid-cols-2">
                            <div class="bg-white px-3 py-2">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Global disclaimers</p>
                                <p class="mt-0.5 text-xs text-slate-600">Always applied after totals on every estimate and invoice.</p>
                            </div>
                            <div class="bg-white px-3 py-2">
                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Billing class disclaimers</p>
                                <p class="mt-0.5 text-xs text-slate-600">Applied automatically from the customer&apos;s classification.</p>
                            </div>
                        </div>

                        <label class="block text-xs font-medium text-slate-500">
                            Global estimate disclaimer
                            <textarea name="estimate_disclaimer" rows="5" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">{{ old('estimate_disclaimer', $settings->estimate_disclaimer) }}</textarea>
                        </label>
                        <label class="block text-xs font-medium text-slate-500">
                            Global invoice disclaimer
                            <textarea name="invoice_disclaimer" rows="5" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">{{ old('invoice_disclaimer', $settings->invoice_disclaimer) }}</textarea>
                        </label>

                        <div class="border-t border-slate-200 pt-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Billing class disclaimers</p>
                            <p class="mt-1 text-xs text-slate-500">One disclaimer per configured billing class. Advisors do not pick these manually.</p>
                            <div class="mt-3 grid gap-3">
                                @foreach ($settings->customerTypeRows() as $customerType)
                                    @php
                                        $typeKey = mb_strtolower($customerType['name']);
                                        $typeDisclaimer = old("customer_type_disclaimers.$typeKey", $settings->customerTypeDisclaimerMap()[$typeKey] ?? '');
                                    @endphp
                                    <label class="block text-xs font-medium text-slate-500">
                                        {{ $customerType['name'] }}
                                        <textarea name="customer_type_disclaimers[{{ $typeKey }}]" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">{{ $typeDisclaimer }}</textarea>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <label class="block text-xs font-medium text-slate-500">
                            Authorization language
                            <textarea name="authorization_language" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">{{ old('authorization_language', $settings->authorization_language) }}</textarea>
                        </label>

                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="portal_signature_required"
                                value="1"
                                @checked(old('portal_signature_required', $settings->portal_signature_required))
                                class="rounded border-slate-300 text-slate-950 focus:ring-slate-400"
                            >
                            <span>Require customer signature on portal authorization</span>
                        </label>
                        <p class="text-xs text-slate-500">When enabled, customers must sign and acknowledge authorization language before submitting portal approval.</p>

                        <label class="block text-xs font-medium text-slate-500">
                            Recommendation disclaimer
                            <span class="font-normal text-slate-400">(concern narrative context, not mixed with authorization)</span>
                            <textarea name="recommendation_disclaimer" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">{{ old('recommendation_disclaimer', $settings->recommendation_disclaimer) }}</textarea>
                        </label>

                        <label class="block max-w-xs text-xs font-medium text-slate-500">
                            Estimate validity days
                            <input name="estimate_validity_days" value="{{ old('estimate_validity_days', $settings->estimate_validity_days) }}" required inputmode="numeric" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        </label>
                    </div>
                    <div class="mt-6 flex justify-end border-t border-slate-200 pt-4">
                        <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                            Save Document Settings
                        </button>
                    </div>
                    </form>
                </section>

                <section x-show="active === 'workflow'" x-cloak>
                    <div class="border-b border-slate-200 pb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Workflow Defaults</p>
                        <h2 class="text-base font-black text-slate-950">Check In defaults and RO lifecycle</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Shop-wide check-in posture and the repair order status catalog that drives workboards and lifecycle moves.</p>
                    </div>

                    <div class="mt-4 grid gap-px border border-slate-300 bg-slate-300 text-sm sm:grid-cols-4">
                        <button
                            type="button"
                            @click="setWorkflowTab('defaults')"
                            :class="workflowTab === 'defaults' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
                            class="px-3 py-2 text-left font-semibold"
                        >Check In defaults</button>
                        <button
                            type="button"
                            @click="setWorkflowTab('statuses')"
                            :class="workflowTab === 'statuses' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
                            class="px-3 py-2 text-left font-semibold"
                        >RO statuses</button>
                        <button
                            type="button"
                            @click="setWorkflowTab('inspections')"
                            :class="workflowTab === 'inspections' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
                            class="px-3 py-2 text-left font-semibold"
                        >Inspection checklists</button>
                        <button
                            type="button"
                            @click="setWorkflowTab('saved-work')"
                            :class="workflowTab === 'saved-work' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'"
                            class="px-3 py-2 text-left font-semibold"
                        >Common Jobs</button>
                    </div>

                    <div x-show="workflowTab === 'defaults'" x-cloak>
                        <div class="mt-4 border-b border-slate-100 pb-2">
                            <h3 class="text-sm font-black text-slate-950">Check In defaults</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Pre-select visit posture, recommendation intent, and note visibility for new work.</p>
                        </div>
                        @include('operations.settings.partials.workflow-intake-defaults')
                    </div>

                    <div x-show="workflowTab === 'statuses'" x-cloak>
                        <div class="mt-4 border-b border-slate-100 pb-2">
                            <h3 class="text-sm font-black text-slate-950">RO status catalog</h3>
                            <p class="mt-0.5 text-xs text-slate-500">LNP workflow matrix — display names, workboard lanes, and role-gated lifecycle moves.</p>
                        </div>
                        @include('operations.settings.partials.ro-status-catalog')
                    </div>

                    <div x-show="workflowTab === 'inspections'" x-cloak>
                        <div class="mt-4 border-b border-slate-100 pb-2">
                            <h3 class="text-sm font-black text-slate-950">Inspection checklists</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Mobile technician checklists — item labels, photo requirements, and measurements. Templates seed checklist rows on each RO; findings still live on InspectionItem authority.</p>
                        </div>
                        @include('operations.settings.partials.inspection-template-settings', ['inspectionTemplates' => $inspectionTemplates])
                    </div>

                    <div x-show="workflowTab === 'saved-work'" x-cloak>
                        <div class="mt-4 border-b border-slate-100 pb-2">
                            <h3 class="text-sm font-black text-slate-950">Common Jobs</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Repeat jobs that author a Repair Action plus labor, parts, and fees. After Add Work, the template owns nothing — edit the RO normally.</p>
                        </div>
                        @include('operations.settings.partials.work-template-settings', ['workTemplates' => $workTemplates])
                    </div>
                </section>

                <section x-show="active === 'operations'" x-cloak>
                    <div class="border-b border-slate-200 px-3 py-3">
                        <h3 class="text-sm font-black text-slate-950">Operations</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Advisor work surface — scheduling, station presence, and shop workflow options.</p>
                    </div>

                    <div class="p-3">
                        <div class="border-b border-slate-200 pb-3">
                            <h4 class="text-xs font-black uppercase tracking-[0.08em] text-slate-700">Appointments</h4>
                            <p class="mt-0.5 text-xs text-slate-500">Scheduling surface and Today&apos;s Appointments band on Work.</p>
                        </div>

                        @include('operations.settings.partials.appointments-settings', [
                            'settings' => $settings,
                            'scheduleBays' => $scheduleBays ?? collect(),
                        ])
                    </div>

                    <div class="p-3">
                        @include('operations.settings.partials.operational-profile', ['settings' => $settings])
                    </div>

                    <div class="p-3">
                        @include('operations.settings.partials.shop-csv-import')
                    </div>
                </section>

                <section x-show="active === 'printing'" x-cloak>
                    <div class="border-b border-slate-200 pb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">QZ Tray / Brother QL</p>
                        <h2 class="text-base font-black text-slate-950">Label printing authority</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Printer names must match the OS queue exactly. Defaults: Brother QL-800, 62 × 38.1 mm.</p>
                    </div>

                    <div class="mt-3 grid gap-px border border-slate-300 bg-slate-300 text-sm sm:grid-cols-2">
                        <button type="button" @click="setPrintingTab('printers')" :class="printingTab === 'printers' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Printers &amp; labels</button>
                        <button type="button" @click="setPrintingTab('certificates')" :class="printingTab === 'certificates' ? 'bg-slate-950 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-950'" class="px-3 py-2 text-left font-semibold">Certificates &amp; setup</button>
                    </div>

                    <div x-show="printingTab === 'printers'" class="mt-4">
                    <form method="POST" action="{{ route('operations.settings.shop.printing.update') }}">
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="flex items-start gap-2 text-xs font-medium text-slate-500 md:col-span-2">
                                <input type="hidden" name="qz_printing_enabled" value="0">
                                <input type="checkbox" name="qz_printing_enabled" value="1" @checked(old('qz_printing_enabled', $settings->qz_printing_enabled)) class="mt-0.5 rounded border-slate-300 text-slate-800">
                                <span>Enable QZ label printing on repair orders</span>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Key tag printer
                                <input type="text" name="qz_printing_key_tag_printer" value="{{ old('qz_printing_key_tag_printer', $settings->qz_printing_key_tag_printer) }}" placeholder="Brother QL-800" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Oil sticker printer
                                <input type="text" name="qz_printing_oil_sticker_printer" value="{{ old('qz_printing_oil_sticker_printer', $settings->qz_printing_oil_sticker_printer) }}" placeholder="Same as key tag when empty" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Label width (mm)
                                <input type="number" step="0.1" min="10" max="200" name="qz_key_tag_label_width_mm" value="{{ old('qz_key_tag_label_width_mm', $settings->qz_key_tag_label_width_mm) }}" placeholder="62" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Label height (mm)
                                <input type="number" step="0.1" min="10" max="200" name="qz_key_tag_label_height_mm" value="{{ old('qz_key_tag_label_height_mm', $settings->qz_key_tag_label_height_mm) }}" placeholder="38.1" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Label orientation
                                <select name="qz_key_tag_orientation" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    @php
                                        $labelOrientation = old('qz_key_tag_orientation', $settings->qz_key_tag_orientation ?? config('printing.key_tag_qz_orientation', 'portrait'));
                                    @endphp
                                    <option value="portrait" @selected($labelOrientation === 'portrait')>Portrait (QL-800 default)</option>
                                    <option value="landscape" @selected($labelOrientation === 'landscape')>Landscape</option>
                                    <option value="auto" @selected($labelOrientation === 'auto')>Auto (wide label → portrait)</option>
                                </select>
                                <span class="mt-1 block font-normal text-slate-400">Use Portrait for Brother QL-800 62×38.1 mm key tags and oil stickers.</span>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Label media
                                <select name="qz_key_tag_media_type" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="mono" @selected(old('qz_key_tag_media_type', $settings->qz_key_tag_media_type ?? 'mono') === 'mono')>Monochrome</option>
                                    <option value="red_black" @selected(old('qz_key_tag_media_type', $settings->qz_key_tag_media_type ?? 'mono') === 'red_black')>Red / black (DK)</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Key tag VIN display
                                <select name="qz_key_tag_vin_display" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="last6" @selected(old('qz_key_tag_vin_display', $settings->qz_key_tag_vin_display) === 'last6')>Last 6</option>
                                    <option value="last8" @selected(old('qz_key_tag_vin_display', $settings->qz_key_tag_vin_display) === 'last8')>Last 8</option>
                                    <option value="full" @selected(old('qz_key_tag_vin_display', $settings->qz_key_tag_vin_display) === 'full')>Full VIN</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Raster DPI override
                                <select name="qz_raster_dpi" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                                    <option value="" @selected(old('qz_raster_dpi', $settings->qz_raster_dpi) === null)>Auto</option>
                                    <option value="203" @selected((int) old('qz_raster_dpi', $settings->qz_raster_dpi) === 203)>203</option>
                                    <option value="300" @selected((int) old('qz_raster_dpi', $settings->qz_raster_dpi) === 300)>300</option>
                                </select>
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Oil change interval (miles)
                                <input type="number" min="1000" max="50000" name="oil_change_interval_miles" value="{{ old('oil_change_interval_miles', $settings->oil_change_interval_miles ?? 5000) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                            <label class="block text-xs font-medium text-slate-500">
                                Oil due months
                                <input type="number" min="1" max="24" name="oil_change_sticker_next_due_months" value="{{ old('oil_change_sticker_next_due_months', $settings->oil_change_sticker_next_due_months ?? 6) }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                            </label>
                        </div>
                        <div class="mt-6 flex justify-end border-t border-slate-200 pt-4">
                            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                                Save Printing
                            </button>
                        </div>
                    </form>
                    </div>

                    <div x-show="printingTab === 'certificates'" x-cloak class="mt-4">
                        @include('operations.settings.partials.printing-qz-certificates', ['qzPrintingReference' => $qzPrintingReference ?? []])
                    </div>
                </section>

                @can(App\Ark\Runtime\Authorization\ArkCapability::StaffManage->value)
                    @include('operations.settings.partials.staff')
                @endcan

                @include('operations.settings.partials.dragon-memory', [
                    'dragonMemories' => $dragonMemories ?? collect(),
                ])

                @include('operations.settings.partials.runtime-health', [
                    'telephonyHealth' => $telephonyHealth,
                    'settings' => $settings,
                ])
                </div>
            </div>
        </div>

        <p class="text-right text-[10px] text-slate-400">
            <a
                href="{{ route('operations.settings.shop.edit', ['section' => 'runtime-health']) }}"
                class="hover:text-slate-600"
                @click.prevent="setActive('runtime-health')"
            >Runtime health</a>
        </p>
    </section>
</x-operations.app>
