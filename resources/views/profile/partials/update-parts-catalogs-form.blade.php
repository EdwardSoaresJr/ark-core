@php
    use App\Ark\Operations\Parts\PartsCatalogButtonColor;
    use App\Ark\Operations\Parts\PartsCatalogLinks;

    $partsCatalogs = $partsCatalogs ?? [];
    $partsCatalogLinks = $partsCatalogLinks ?? [];
    $shopRepairLinkUrl = $shopRepairLinkUrl ?? null;
    $shopNexpartUrl = $shopNexpartUrl ?? null;
    $userRepairLinkUrl = $userRepairLinkUrl ?? null;
    $userNexpartUrl = $userNexpartUrl ?? null;
    $userRepairLinkColor = $userRepairLinkColor ?? PartsCatalogButtonColor::BLUE;
    $userNexpartColor = $userNexpartColor ?? PartsCatalogButtonColor::NAVY;
    $defaultChoices = collect($partsCatalogs)
        ->filter(fn (array $catalog): bool => ($catalog['mode'] ?? 'catalog') === 'catalog' && ($catalog['can_open'] ?? false))
        ->values();

    if ($defaultChoices->isEmpty()) {
        $defaultChoices = collect($partsCatalogs)->values();
    }

    $currentDefault = old(
        'default_parts_catalog',
        $user->default_parts_catalog ?: ($defaultChoices[0]['key'] ?? ''),
    );
    $colorLabels = PartsCatalogButtonColor::labels();
@endphp

<section>
    <div class="mb-3">
        <div class="subheader">Estimate toolbar</div>
        <h2 class="h3 mb-1">Parts catalogs</h2>
        <p class="text-secondary mb-0">
            Default catalog for new repair orders, plus extra catalog buttons and new-tab links. PartsTech is managed by ARK Platform and cannot be edited here.
        </p>
    </div>

    <form method="post" action="{{ route('profile.catalogs.update') }}" class="d-flex flex-column gap-3" data-profile-catalogs-form>
        @csrf
        @method('patch')

        <div>
            <label for="profile-default-parts-catalog" class="form-label">Default catalog</label>
            <select
                id="profile-default-parts-catalog"
                name="default_parts_catalog"
                class="form-select"
            >
                @foreach ($defaultChoices as $catalog)
                    <option value="{{ $catalog['key'] }}" @selected($currentDefault === $catalog['key'])>
                        {{ $catalog['label'] }}{{ $catalog['can_open'] ? '' : ' (add URL)' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">PartsTech</div>
                <p class="mb-0">ARK Platform catalog. Open and Pull Cart stay on the estimate toolbar. The launch URL cannot be edited.</p>
            </div>
        </div>

        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">RepairLink</div>
                <p class="small text-secondary">Opens in a new tab. It does not replace PartsTech on the main catalog button.</p>
                <div class="row g-2">
                    <div class="col-12 col-md">
                        <label for="profile-repairlink-url" class="form-label">Launch URL</label>
                        <input
                            type="text"
                            id="profile-repairlink-url"
                            name="repairlink_url"
                            value="{{ old('repairlink_url', $userRepairLinkUrl ?? '') }}"
                            @class(['form-control font-monospace', 'is-invalid' => $errors->has('repairlink_url')])
                            placeholder="{{ $shopRepairLinkUrl ?: 'https://repairlinkshop.com' }}"
                            autocomplete="off"
                        >
                        @error('repairlink_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="profile-repairlink-color" class="form-label">Button color</label>
                        <select id="profile-repairlink-color" name="repairlink_color" class="form-select">
                            @foreach ($colorLabels as $colorKey => $colorLabel)
                                <option value="{{ $colorKey }}" @selected(old('repairlink_color', $userRepairLinkColor) === $colorKey)>{{ $colorLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-hint mt-2">
                    Leave blank to use the shop RepairLink address{{ $shopRepairLinkUrl ? ' ('.$shopRepairLinkUrl.')' : '' }}.
                </div>
            </div>
        </div>

        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">Nexpart</div>
                <p class="small text-secondary">Catalog on the estimate button only when ARK Platform parts catalog is paid. Otherwise it opens in a new tab.</p>
                <div class="row g-2">
                    <div class="col-12 col-md">
                        <label for="profile-nexpart-url" class="form-label">Launch URL</label>
                        <input
                            type="text"
                            id="profile-nexpart-url"
                            name="nexpart_url"
                            value="{{ old('nexpart_url', $userNexpartUrl ?? '') }}"
                            @class(['form-control font-monospace', 'is-invalid' => $errors->has('nexpart_url')])
                            placeholder="{{ $shopNexpartUrl ?: 'https://' }}"
                            autocomplete="off"
                        >
                        @error('nexpart_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="profile-nexpart-color" class="form-label">Button color</label>
                        <select id="profile-nexpart-color" name="nexpart_color" class="form-select">
                            @foreach ($colorLabels as $colorKey => $colorLabel)
                                <option value="{{ $colorKey }}" @selected(old('nexpart_color', $userNexpartColor) === $colorKey)>{{ $colorLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-hint mt-2">
                    Leave blank to use the shop Nexpart address{{ $shopNexpartUrl ? ' ('.$shopNexpartUrl.')' : '' }}.
                </div>
            </div>
        </div>

        <div>
            <div class="subheader">My catalog links</div>
            <p class="small text-secondary">O’Reilly Pro, First Call, AutoZone Pro, or any other https:// catalog. Catalog sits on the estimate button. Link opens in a new tab. These are only on your estimate toolbar.</p>

            @error('catalog_links')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <div class="d-flex flex-column gap-2">
                @foreach ($partsCatalogLinks as $index => $link)
                    @php
                        $linkMode = old('links.'.$index.'.mode', $link['mode'] ?? 'link');
                        $linkColor = old('links.'.$index.'.color', $link['color'] ?? 'slate');
                    @endphp
                    <div class="card card-sm" data-profile-catalog-link>
                        <div class="card-body">
                            <input type="hidden" name="links[{{ $index }}][key]" value="{{ $link['key'] }}">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label for="profile-link-{{ $index }}-label" class="form-label">Name</label>
                                    <input
                                        type="text"
                                        id="profile-link-{{ $index }}-label"
                                        name="links[{{ $index }}][label]"
                                        value="{{ old('links.'.$index.'.label', $link['label']) }}"
                                        maxlength="64"
                                        class="form-control"
                                    >
                                </div>
                                <div class="col-12 col-sm-6 col-lg-4">
                                    <label for="profile-link-{{ $index }}-url" class="form-label">URL</label>
                                    <input
                                        type="text"
                                        id="profile-link-{{ $index }}-url"
                                        name="links[{{ $index }}][url]"
                                        value="{{ old('links.'.$index.'.url', $link['url']) }}"
                                        class="form-control font-monospace"
                                        autocomplete="off"
                                    >
                                </div>
                                <div class="col-6 col-lg-2">
                                    <label for="profile-link-{{ $index }}-mode" class="form-label">Type</label>
                                    <select id="profile-link-{{ $index }}-mode" name="links[{{ $index }}][mode]" class="form-select">
                                        <option value="{{ PartsCatalogLinks::MODE_LINK }}" @selected($linkMode === PartsCatalogLinks::MODE_LINK)>Link (new tab)</option>
                                        <option value="{{ PartsCatalogLinks::MODE_CATALOG }}" @selected($linkMode === PartsCatalogLinks::MODE_CATALOG)>Catalog</option>
                                    </select>
                                </div>
                                <div class="col-6 col-lg-2">
                                    <label for="profile-link-{{ $index }}-color" class="form-label">Button color</label>
                                    <select id="profile-link-{{ $index }}-color" name="links[{{ $index }}][color]" class="form-select">
                                        @foreach ($colorLabels as $colorKey => $colorLabel)
                                            <option value="{{ $colorKey }}" @selected($linkColor === $colorKey)>{{ $colorLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-lg-1">
                                    <label class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" name="links[{{ $index }}][delete]" value="1">
                                        <span class="form-check-label">Remove</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @php
                    $createMode = old('create.mode', 'link');
                    $createColor = old('create.color', '');
                @endphp
                <div class="card card-sm" data-profile-catalog-link-create>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label for="profile-create-label" class="form-label">Add name</label>
                                <input
                                    type="text"
                                    id="profile-create-label"
                                    name="create[label]"
                                    value="{{ old('create.label') }}"
                                    maxlength="64"
                                    placeholder="O’Reilly Pro"
                                    class="form-control"
                                >
                            </div>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label for="profile-create-url" class="form-label">Add URL</label>
                                <input
                                    type="text"
                                    id="profile-create-url"
                                    name="create[url]"
                                    value="{{ old('create.url') }}"
                                    placeholder="https://"
                                    class="form-control font-monospace"
                                    autocomplete="off"
                                >
                            </div>
                            <div class="col-6 col-lg-2">
                                <label for="profile-create-mode" class="form-label">Type</label>
                                <select id="profile-create-mode" name="create[mode]" class="form-select">
                                    <option value="{{ PartsCatalogLinks::MODE_LINK }}" @selected($createMode === PartsCatalogLinks::MODE_LINK)>Link (new tab)</option>
                                    <option value="{{ PartsCatalogLinks::MODE_CATALOG }}" @selected($createMode === PartsCatalogLinks::MODE_CATALOG)>Catalog</option>
                                </select>
                            </div>
                            <div class="col-6 col-lg-3">
                                <label for="profile-create-color" class="form-label">Button color</label>
                                <select id="profile-create-color" name="create[color]" class="form-select">
                                    <option value="" @selected($createColor === '' || $createColor === null)>Automatic</option>
                                    @foreach ($colorLabels as $colorKey => $colorLabel)
                                        <option value="{{ $colorKey }}" @selected($createColor === $colorKey)>{{ $colorLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">
                Save catalogs
            </button>
        </div>
    </form>
</section>
