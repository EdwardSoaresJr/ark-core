@php
    $integrations = $shopIntegrations ?? \App\Ark\Operations\Settings\ShopIntegrationCredentials::forCurrentShop();
    $platformPartsCatalog = \App\Ark\Platform\Parts\ManagedPartsGate::platformCatalog();
    $catalogColors = \App\Ark\Operations\Parts\PartsCatalogButtonColor::shopMap();
    $partsTechColor = old('partstech_color', $catalogColors['partstech'] ?? \App\Ark\Operations\Parts\PartsCatalogButtonColor::YELLOW);
    $repairLinkColor = old('repairlink_color', $catalogColors['repairlink'] ?? \App\Ark\Operations\Parts\PartsCatalogButtonColor::BLUE);
    $nexpartColor = old('nexpart_color', $catalogColors['nexpart'] ?? \App\Ark\Operations\Parts\PartsCatalogButtonColor::NAVY);
@endphp

<div class="card-body" data-settings-partstech>
    <div style="max-width: 48rem">
        <div class="subheader">PartsTech</div>
        <h3 class="mb-1">Parts catalog</h3>
        @if ($platformPartsCatalog)
            <p class="text-secondary small">
                The shop PartsTech account is managed by ARK Platform. Optional personal seats stay on each person’s profile (avatar menu → Profile).
            </p>
            <div class="alert alert-secondary small" role="status">
                Shop catalog access is granted and configured in ARK Platform. This shop does not store a shop-wide PartsTech password.
            </div>
        @else
            <p class="text-secondary small">
                @if (\App\Ark\Runtime\DemoInstall::isDemo())
                    This shop does not store a shop-wide PartsTech password. Optional personal seats stay on each person’s profile (avatar menu → Profile).
                @else
                    This shop does not store a shop-wide PartsTech password. Connect ARK Platform for shop catalog access. Optional personal seats stay on each person’s profile (avatar menu → Profile).
                    <a href="{{ route('operations.settings.shop.edit', ['section' => 'ark-cloud']) }}">Connect in ARK Platform</a>
                @endif
            </p>
        @endif
        <form method="POST" action="{{ route('operations.settings.shop.partstech.update') }}">
            @csrf
            @method('PATCH')
            <div style="max-width: 20rem">
                @include('operations.settings.partials.parts-catalog-color-select', [
                    'name' => 'partstech_color',
                    'value' => $partsTechColor,
                ])
            </div>
            <div class="d-flex justify-content-end border-top pt-3 mt-3">
                <button type="submit" class="btn btn-primary">
                    Save button color
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card-body" data-settings-repairlink>
    <form method="POST" action="{{ route('operations.settings.shop.repairlink.update') }}" style="max-width: 48rem">
        @csrf
        @method('PATCH')

        <div class="subheader">RepairLink</div>
        <h3 class="mb-1">New-tab catalog link</h3>
        <p class="text-secondary small">
            Opens RepairLink in a new tab from the estimate toolbar. It does not replace PartsTech on the main catalog button. ARK does not sign in, pull a cart, or send vehicle data in the URL. VIN is copied to the clipboard when present.
        </p>

        <div @class(['alert small', 'alert-success' => $integrations->repairLinkConfigured(), 'alert-secondary' => ! $integrations->repairLinkConfigured()]) role="status">
            @if ($integrations->repairLinkConfigured())
                RepairLink is available on the estimate toolbar.
            @else
                RepairLink stays closed until it is enabled with an https:// launch address.
            @endif
        </div>

        <label class="form-check form-switch">
            <input type="hidden" name="repairlink_enabled" value="0">
            <input type="checkbox" name="repairlink_enabled" value="1" @checked(old('repairlink_enabled', $settings->repairlink_enabled ?? false)) class="form-check-input">
            <span class="form-check-label">Show RepairLink on the estimate toolbar</span>
        </label>

        <div class="row g-3">
            <div class="col-12 col-md-8">
                <label for="settings-repairlink-url" class="form-label">Launch URL</label>
                <input
                    id="settings-repairlink-url"
                    type="text"
                    name="repairlink_url"
                    value="{{ old('repairlink_url', $settings->repairlink_url ?? '') }}"
                    @class(['form-control font-monospace', 'is-invalid' => $errors->has('repairlink_url')])
                    placeholder="https://repairlinkshop.com"
                    autocomplete="off"
                >
                @error('repairlink_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-hint">HTTPS only. Enter the shop’s RepairLink address. ARK will add https:// if the scheme is missing.</div>
            </div>

            <div class="col-12 col-md-4">
                @include('operations.settings.partials.parts-catalog-color-select', [
                    'name' => 'repairlink_color',
                    'value' => $repairLinkColor,
                ])
            </div>
        </div>

        <div class="d-flex justify-content-end border-top pt-3 mt-3">
            <button type="submit" class="btn btn-primary">
                Save RepairLink settings
            </button>
        </div>
    </form>
</div>

<div class="card-body" data-settings-nexpart>
    <form method="POST" action="{{ route('operations.settings.shop.nexpart.update') }}" style="max-width: 48rem">
        @csrf
        @method('PATCH')

        <div class="subheader">Nexpart</div>
        <h3 class="mb-1">Catalog or new-tab link</h3>
        <p class="text-secondary small">
            @if (\App\Ark\Runtime\DemoInstall::isDemo())
                Nexpart opens in a new tab like RepairLink. ARK does not sign in or pull a Nexpart cart.
            @else
                If this shop pays for ARK Platform parts catalog, Nexpart sits on the estimate catalog button (Open and VIN copy; no cart pull). Otherwise it opens in a new tab like RepairLink. ARK does not sign in or pull a Nexpart cart.
            @endif
        </p>

        <div @class(['alert small', 'alert-success' => $integrations->nexpartConfigured(), 'alert-secondary' => ! $integrations->nexpartConfigured()]) role="status">
            @if ($integrations->nexpartConfigured())
                Nexpart is available on the estimate toolbar.
            @else
                Nexpart stays closed until it is enabled with an https:// launch address.
            @endif
        </div>

        <label class="form-check form-switch">
            <input type="hidden" name="nexpart_enabled" value="0">
            <input type="checkbox" name="nexpart_enabled" value="1" @checked(old('nexpart_enabled', $settings->nexpart_enabled ?? false)) class="form-check-input">
            <span class="form-check-label">Enable Nexpart for this shop</span>
        </label>

        <div class="row g-3">
            <div class="col-12 col-md-8">
                <label for="settings-nexpart-url" class="form-label">Launch URL</label>
                <input
                    id="settings-nexpart-url"
                    type="text"
                    name="nexpart_url"
                    value="{{ old('nexpart_url', $settings->nexpart_url ?? '') }}"
                    @class(['form-control font-monospace', 'is-invalid' => $errors->has('nexpart_url')])
                    placeholder="https://"
                    autocomplete="off"
                >
                @error('nexpart_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-hint">HTTPS only. ARK will add https:// if the scheme is missing.</div>
            </div>

            <div class="col-12 col-md-4">
                @include('operations.settings.partials.parts-catalog-color-select', [
                    'name' => 'nexpart_color',
                    'value' => $nexpartColor,
                ])
            </div>
        </div>

        <div class="d-flex justify-content-end border-top pt-3 mt-3">
            <button type="submit" class="btn btn-primary">
                Save Nexpart settings
            </button>
        </div>
    </form>
</div>

@php
    $shopCatalogLinks = \App\Ark\Operations\Parts\PartsCatalogLinks::customForShop();
@endphp

<div class="card-body" data-settings-catalog-links>
    <form method="POST" action="{{ route('operations.settings.shop.catalog-links.update') }}">
        @csrf
        @method('PATCH')

        <div class="subheader">More catalogs</div>
        <h3 class="mb-1">Shop catalog links</h3>
        <p class="text-secondary small" style="max-width: 48rem">
            Add O’Reilly Pro, First Call, AutoZone Pro, or any other https:// catalog. Choose Catalog to put it on the estimate button like PartsTech, or Link to open it in a new tab. PartsTech cannot be added or edited here.
        </p>

        @error('catalog_links')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror

        @if (count($shopCatalogLinks) > 0)
            <div class="list-group mb-3">
                @foreach ($shopCatalogLinks as $index => $link)
                    <div class="list-group-item" data-catalog-link="{{ $link['key'] }}">
                        <input type="hidden" name="links[{{ $index }}][key]" value="{{ $link['key'] }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label for="settings-catalog-link-{{ $index }}-label" class="form-label">Name</label>
                                <input
                                    id="settings-catalog-link-{{ $index }}-label"
                                    type="text"
                                    name="links[{{ $index }}][label]"
                                    value="{{ old('links.'.$index.'.label', $link['label']) }}"
                                    maxlength="64"
                                    class="form-control"
                                >
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label for="settings-catalog-link-{{ $index }}-url" class="form-label">Launch URL</label>
                                <input
                                    id="settings-catalog-link-{{ $index }}-url"
                                    type="text"
                                    name="links[{{ $index }}][url]"
                                    value="{{ old('links.'.$index.'.url', $link['url']) }}"
                                    class="form-control font-monospace"
                                    autocomplete="off"
                                >
                            </div>
                            <div class="col-6 col-lg-2">
                                @include('operations.settings.partials.parts-catalog-mode-select', [
                                    'name' => 'links['.$index.'][mode]',
                                    'value' => old('links.'.$index.'.mode', $link['mode'] ?? 'link'),
                                ])
                            </div>
                            <div class="col-6 col-lg-2">
                                @include('operations.settings.partials.parts-catalog-color-select', [
                                    'name' => 'links['.$index.'][color]',
                                    'value' => old('links.'.$index.'.color', $link['color'] ?? 'slate'),
                                ])
                            </div>
                            <div class="col-12 col-lg-1">
                                <label class="form-check mb-2">
                                    <input type="checkbox" name="links[{{ $index }}][delete]" value="1" class="form-check-input">
                                    <span class="form-check-label">Remove</span>
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card card-sm" data-catalog-link-add>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label for="settings-catalog-link-create-label" class="form-label">Add name</label>
                        <input
                            id="settings-catalog-link-create-label"
                            type="text"
                            name="create[label]"
                            value="{{ old('create.label') }}"
                            maxlength="64"
                            placeholder="O’Reilly Pro"
                            class="form-control"
                        >
                    </div>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <label for="settings-catalog-link-create-url" class="form-label">Add URL</label>
                        <input
                            id="settings-catalog-link-create-url"
                            type="text"
                            name="create[url]"
                            value="{{ old('create.url') }}"
                            placeholder="https://"
                            class="form-control font-monospace"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-6 col-lg-2">
                        @include('operations.settings.partials.parts-catalog-mode-select', [
                            'name' => 'create[mode]',
                            'value' => old('create.mode', 'link'),
                        ])
                    </div>
                    <div class="col-6 col-lg-3">
                        @include('operations.settings.partials.parts-catalog-color-select', [
                            'name' => 'create[color]',
                            'value' => old('create.color', ''),
                            'allowAutomatic' => true,
                        ])
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end border-top pt-3 mt-3">
            <button type="submit" class="btn btn-primary">
                Save catalog links
            </button>
        </div>
    </form>
</div>
