@php
    use App\Ark\Operations\EstimatePricing\ShopTaxCatalog;

    $taxEnabled = (bool) old('tax_enabled', $settings->tax_enabled);
    $presentation = old('tax_document_presentation', $settings->taxDocumentPresentation());
    $profiles = old('tax_profiles', $settings->taxProfiles());
    if (! is_array($profiles) || $profiles === []) {
        $profiles = $settings->taxProfiles();
    }
    try {
        $profiles = ShopTaxCatalog::normalizeProfiles(
            collect($profiles)->map(function ($profile): array {
                $profile = is_array($profile) ? $profile : [];
                $profile['is_active'] = filter_var($profile['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $profile['is_default'] = filter_var($profile['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $profile['taxable_labor'] = filter_var($profile['taxable_labor'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $profile['taxable_parts'] = filter_var($profile['taxable_parts'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $profile['taxable_shop_fees'] = filter_var($profile['taxable_shop_fees'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $profile['components'] = collect($profile['components'] ?? [])->map(function ($component): array {
                    $component = is_array($component) ? $component : [];
                    $component['is_active'] = filter_var($component['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

                    return $component;
                })->all();

                return $profile;
            })->all()
        );
    } catch (\Illuminate\Validation\ValidationException) {
        $profiles = $settings->taxProfiles();
    }
    $selectedKey = old('selected_tax_profile_key', collect($profiles)->firstWhere('is_default', true)['key'] ?? $profiles[0]['key']);
    $defaultProfile = collect($profiles)->firstWhere('is_default', true) ?? $profiles[0];
@endphp

<form
    method="POST"
    action="{{ route('operations.settings.shop.tax.update') }}"
    data-settings-tax
    x-data="{ taxEnabled: @js($taxEnabled), selectedKey: @js($selectedKey), presentation: @js($presentation) }"
>
    @csrf
    @method('PATCH')

    {{-- Legacy projection fields: keep existing scrapers/tests and TaxProfile schema 1 projection in sync. --}}
    <input type="hidden" name="tax_label" id="settings-tax-label" value="{{ $defaultProfile['name'] }}">
    <input type="hidden" name="default_tax_rate" id="settings-tax-rate" value="{{ ShopTaxCatalog::combinedRate($defaultProfile) }}">
    <input type="hidden" name="taxable_labor" value="{{ $defaultProfile['taxable_labor'] ? '1' : '0' }}">
    <input type="hidden" name="taxable_parts" value="{{ $defaultProfile['taxable_parts'] ? '1' : '0' }}">
    <input type="hidden" name="taxable_shop_fees" value="{{ $defaultProfile['taxable_shop_fees'] ? '1' : '0' }}">

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h3 class="mb-1">Sales tax profiles</h3>
            <p class="text-secondary small mb-0">
                Configure named jurisdictions with individual tax components. New repair orders stamp the
                <strong>default</strong> profile’s combined rate (TaxProfile schema 1). Changing these settings
                does not reprice existing open or closed ROs.
            </p>
        </div>
        <label class="form-check form-switch mb-0">
            <input type="hidden" name="tax_enabled" value="0">
            <input type="checkbox" name="tax_enabled" value="1" x-model="taxEnabled" @checked($taxEnabled) class="form-check-input">
            <span class="form-check-label">Charge sales tax</span>
        </label>
    </div>

    <div class="card card-sm mb-3" data-settings-tax-presentation>
        <div class="card-body">
            <div class="subheader mb-1">Customer document presentation</div>
            <p class="text-secondary small">
                Shop-wide preference for how tax appears on estimates and invoices. Same underlying cents either way.
                Itemized document rendering lands in a later slice; the preference is stored now without rewriting issued documents.
            </p>
            <div class="d-flex flex-wrap gap-3">
                <label class="form-check mb-0">
                    <input type="radio" class="form-check-input" name="tax_document_presentation" value="combined" x-model="presentation" @checked($presentation === 'combined')>
                    <span class="form-check-label">Combined — one Sales Tax line</span>
                </label>
                <label class="form-check mb-0">
                    <input type="radio" class="form-check-input" name="tax_document_presentation" value="itemized" x-model="presentation" @checked($presentation === 'itemized')>
                    <span class="form-check-label">Itemized — named components</span>
                </label>
            </div>
        </div>
    </div>

    <p x-show="! taxEnabled" @if ($taxEnabled) x-cloak @endif class="text-secondary small">Tax is off. Estimates and invoices show no sales tax. Turn it on to edit profiles.</p>

    <div x-show="taxEnabled" @unless ($taxEnabled) x-cloak @endunless>
        <div class="ops-financial-master-detail">
            <div class="ops-financial-master-detail__nav">
                <nav class="card sticky-lg-top" style="top: 1rem" aria-label="Tax profiles" data-tax-profile-nav>
                    <div class="card-header py-2">
                        <div class="subheader">Tax profiles</div>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($profiles as $profile)
                            <button
                                type="button"
                                class="list-group-item list-group-item-action"
                                :class="{ 'active': selectedKey === @js($profile['key']) }"
                                @click="selectedKey = @js($profile['key'])"
                                data-tax-profile-tab="{{ $profile['key'] }}"
                            >
                                {{ $profile['name'] }}
                                @if ($profile['is_default'])
                                    <span class="badge bg-secondary-lt ms-1">Default</span>
                                @endif
                                @unless ($profile['is_active'])
                                    <span class="badge bg-yellow-lt ms-1">Inactive</span>
                                @endunless
                            </button>
                        @endforeach
                    </div>
                </nav>
                <p class="small text-secondary mt-2 mb-0 d-lg-none">Select a profile above, then edit its components.</p>
            </div>

            <div class="ops-financial-master-detail__editor">
                @foreach ($profiles as $profileIndex => $profile)
                    <div
                        data-tax-profile-editor="{{ $profile['key'] }}"
                        x-show="selectedKey === @js($profile['key'])"
                        @if ($profile['key'] !== $selectedKey) x-cloak @endif
                    >
                        <input type="hidden" name="tax_profiles[{{ $profileIndex }}][key]" value="{{ $profile['key'] }}">
                        <input type="hidden" name="tax_profiles[{{ $profileIndex }}][is_active]" value="{{ $profile['is_active'] ? '1' : '0' }}">
                        <input type="hidden" name="tax_profiles[{{ $profileIndex }}][is_default]" value="{{ $profile['is_default'] ? '1' : '0' }}">

                        <div class="card card-sm">
                            <div class="card-header">
                                <div class="row g-2 align-items-center flex-fill">
                                    <div class="col">
                                        <label class="form-label visually-hidden" for="tax-profile-name-{{ $profile['key'] }}">Profile name</label>
                                        <input
                                            id="tax-profile-name-{{ $profile['key'] }}"
                                            class="form-control fw-medium"
                                            name="tax_profiles[{{ $profileIndex }}][name]"
                                            value="{{ $profile['name'] }}"
                                            required
                                            maxlength="64"
                                        >
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-check mb-0">
                                            <input
                                                type="radio"
                                                name="default_tax_profile_key"
                                                value="{{ $profile['key'] }}"
                                                @checked($profile['is_default'])
                                                class="form-check-input"
                                                onchange="this.form.querySelectorAll('[name^=\'tax_profiles\'][name$=\'[is_default]\']').forEach((el) => { el.value = '0'; }); this.form.querySelector('[name=\'tax_profiles[{{ $profileIndex }}][is_default]\']').value = '1';"
                                            >
                                            <span class="form-check-label">Default</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body vstack gap-3">
                                <div class="row g-2">
                                    <div class="col-6 col-md-3">
                                        <label class="form-label" for="tax-profile-state-{{ $profile['key'] }}">State</label>
                                        <input id="tax-profile-state-{{ $profile['key'] }}" class="form-control" maxlength="2" name="tax_profiles[{{ $profileIndex }}][state]" value="{{ $profile['state'] }}">
                                    </div>
                                    <div class="col-12 col-md-5">
                                        <label class="form-label" for="tax-profile-cities-{{ $profile['key'] }}">Cities</label>
                                        <input id="tax-profile-cities-{{ $profile['key'] }}" class="form-control" name="tax_profiles[{{ $profileIndex }}][cities]" value="{{ implode(', ', $profile['cities'] ?? []) }}" placeholder="Comma-separated">
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="tax-profile-zips-{{ $profile['key'] }}">ZIP codes</label>
                                        <input id="tax-profile-zips-{{ $profile['key'] }}" class="form-control" name="tax_profiles[{{ $profileIndex }}][zip_codes]" value="{{ implode(', ', $profile['zip_codes'] ?? []) }}" placeholder="Convenience only">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="tax-profile-area-{{ $profile['key'] }}">Service area notes</label>
                                        <input id="tax-profile-area-{{ $profile['key'] }}" class="form-control" maxlength="255" name="tax_profiles[{{ $profileIndex }}][service_area_description]" value="{{ $profile['service_area_description'] }}" placeholder="Optional — not used for automatic rate selection">
                                    </div>
                                </div>

                                <fieldset>
                                    <legend class="form-label">Apply this profile’s tax to</legend>
                                    <div class="d-flex flex-wrap gap-4">
                                        <label class="form-check mb-0">
                                            <input type="hidden" name="tax_profiles[{{ $profileIndex }}][taxable_labor]" value="0">
                                            <input type="checkbox" class="form-check-input" name="tax_profiles[{{ $profileIndex }}][taxable_labor]" value="1" @checked($profile['taxable_labor'])>
                                            <span class="form-check-label">Tax labor</span>
                                        </label>
                                        <label class="form-check mb-0">
                                            <input type="hidden" name="tax_profiles[{{ $profileIndex }}][taxable_parts]" value="0">
                                            <input type="checkbox" class="form-check-input" name="tax_profiles[{{ $profileIndex }}][taxable_parts]" value="1" @checked($profile['taxable_parts'])>
                                            <span class="form-check-label">Tax parts</span>
                                        </label>
                                        <label class="form-check mb-0">
                                            <input type="hidden" name="tax_profiles[{{ $profileIndex }}][taxable_shop_fees]" value="0">
                                            <input type="checkbox" class="form-check-input" name="tax_profiles[{{ $profileIndex }}][taxable_shop_fees]" value="1" @checked($profile['taxable_shop_fees'])>
                                            <span class="form-check-label">Tax shop fees</span>
                                        </label>
                                    </div>
                                </fieldset>

                                <div>
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <div>
                                            <div class="subheader mb-0">Tax components</div>
                                            <div class="small text-secondary">Combined rate: <strong>{{ ShopTaxCatalog::combinedRate($profile) }}%</strong></div>
                                        </div>
                                    </div>
                                    <div class="table-responsive border rounded">
                                        <table class="table table-vcenter table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th style="width: 7rem">Rate %</th>
                                                    <th style="width: 9rem">Type</th>
                                                    <th>Reporting code</th>
                                                    <th class="w-1">Active</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($profile['components'] as $componentIndex => $component)
                                                    <tr>
                                                        <td>
                                                            <input type="hidden" name="tax_profiles[{{ $profileIndex }}][components][{{ $componentIndex }}][id]" value="{{ $component['id'] }}">
                                                            <input type="hidden" name="tax_profiles[{{ $profileIndex }}][components][{{ $componentIndex }}][sort_order]" value="{{ $componentIndex + 1 }}">
                                                            <input class="form-control form-control-sm" required maxlength="64" name="tax_profiles[{{ $profileIndex }}][components][{{ $componentIndex }}][name]" value="{{ $component['name'] }}">
                                                        </td>
                                                        <td>
                                                            <input class="form-control form-control-sm font-monospace" inputmode="decimal" required name="tax_profiles[{{ $profileIndex }}][components][{{ $componentIndex }}][rate]" value="{{ $component['rate'] }}">
                                                        </td>
                                                        <td>
                                                            <select class="form-select form-select-sm" name="tax_profiles[{{ $profileIndex }}][components][{{ $componentIndex }}][jurisdiction_type]">
                                                                @foreach (ShopTaxCatalog::JURISDICTION_TYPES as $type)
                                                                    <option value="{{ $type }}" @selected(($component['jurisdiction_type'] ?? 'other') === $type)>{{ str_replace('_', ' ', $type) }}</option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input class="form-control form-control-sm" maxlength="64" name="tax_profiles[{{ $profileIndex }}][components][{{ $componentIndex }}][reporting_code]" value="{{ $component['reporting_code'] }}" placeholder="Optional">
                                                        </td>
                                                        <td>
                                                            <input type="hidden" name="tax_profiles[{{ $profileIndex }}][components][{{ $componentIndex }}][is_active]" value="0">
                                                            <input type="checkbox" class="form-check-input" name="tax_profiles[{{ $profileIndex }}][components][{{ $componentIndex }}][is_active]" value="1" @checked($component['is_active'])>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="form-hint mb-0 mt-2">
                                        Component IDs stay stable. Deactivate a component instead of removing it so historical references remain meaningful.
                                        Add additional profiles/components by saving a copy in a follow-up edit — Slice A stores the catalog without silent deletes.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="border rounded px-3 py-2 mt-3 small text-secondary" data-settings-tax-saved>
        Saved projection for new ROs:
        {{ $settings->tax_enabled ? ShopTaxCatalog::combinedRate($defaultProfile).'% '.$defaultProfile['name'] : 'Tax disabled' }}
        · presentation {{ $settings->taxDocumentPresentation() }}
        · {{ $defaultProfile['taxable_labor'] ? 'labor taxable' : 'labor not taxable' }}
        · {{ $defaultProfile['taxable_parts'] ? 'parts taxable' : 'parts not taxable' }}
        · {{ $defaultProfile['taxable_shop_fees'] ? 'shop fees taxable' : 'shop fees not taxable' }}.
    </div>

    @error('tax_profiles')
        <div class="alert alert-danger mt-3" role="alert">{{ $message }}</div>
    @enderror
    @error('default_tax_rate')
        <div class="invalid-feedback d-block" id="settings-tax-rate-error">{{ $message }}</div>
    @enderror

    <div class="d-flex justify-content-end border-top pt-3 mt-3">
        <button type="submit" class="btn btn-primary">Save Tax Rules</button>
    </div>
</form>
