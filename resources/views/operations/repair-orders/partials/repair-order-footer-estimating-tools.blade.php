{{-- RO-wide estimating tools in the docked footer. Methods and menus belong to the worksheet root scope. --}}
@php
    $partsCatalogs = $tools['parts_catalogs'] ?? [];
    $laborGuides = $tools['labor_guides'] ?? [];
    $partsCatalogDefault = $tools['parts_catalog_default'] ?? null;
    $laborGuideDefault = $tools['labor_guide_default'] ?? null;
    $hasCart = (bool) ($tools['cart'] ?? false);
    $presentActions = $presentActions ?? [];
    $catalogButtons = collect($partsCatalogs)->where('mode', 'catalog')->values();
    $selectedCatalog = $catalogButtons->firstWhere('key', $partsCatalogDefault)
        ?? $catalogButtons->first()
        ?? ($partsCatalogs[0] ?? null);
    $primaryGuide = collect($laborGuides)->firstWhere('key', $laborGuideDefault) ?? ($laborGuides[0] ?? null);
    $catalogColors = "{ yellow: 'btn-outline-yellow', green: 'btn-outline-green', orange: 'btn-outline-orange', red: 'btn-outline-red', blue: 'btn-outline-blue', navy: 'btn-outline-indigo', slate: 'btn-outline-secondary' }";
    $guideColors = "{ rte: 'btn-outline-teal', alldata: 'btn-outline-yellow', prodemand: 'btn-outline-indigo' }";
    $openCart = "window.dispatchEvent(new CustomEvent('ark:partstech-cart-open', { detail: { pull: \$store.partsCart.attention } }))";
    $linkedQuotesAvailable = app(\App\Ark\Operations\Parts\LinkedPartsQuotes::class)->available();
    $linkedQuotePanel = $linkedQuotesAvailable
        ? app(\App\Ark\Operations\Parts\LinkedPartsQuotes::class)->present($repairOrder)
        : null;
    $linkedQuoteLinked = (bool) ($linkedQuotePanel['current'] ?? null);
    $partsCatalogSplit = $linkedQuotesAvailable || count($partsCatalogs) > 1;
@endphp

<div class="d-none d-md-flex align-items-center gap-1" role="group" aria-label="Estimating tools" data-ro-footer-tools>
    <button
        type="button"
        class="btn btn-sm btn-ghost-secondary"
        data-ro-footer-dealer-quote
        title="Import a dealer quote PDF or pasted text onto this estimate"
        @click="window.dispatchEvent(new CustomEvent('ark:dealer-quote-capture-open'))"
    >
        Import
    </button>
    @if ($partsCatalogs !== [])
        <div class="position-relative" data-toolbar-group="parts-catalog" @click.outside="partsCatalogMenu = false">
            <div class="btn-group" role="group" aria-label="Parts catalog">
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="({{ $catalogColors }})[partsCatalogSelected()?.color] || 'btn-outline-azure'"
                    data-parts-catalog="{{ $selectedCatalog['key'] ?? '' }}"
                    :data-parts-catalog="partsCatalogSelected()?.key"
                    @click="openPartsCatalog(partsCatalogSelected()?.key)"
                    :disabled="partsCatalogSelected()?.kind === 'partstech' && partsCatalogSelected()?.mode === 'catalog'
                        ? (partstechCatalogOpening || ! partsCatalogSelected()?.can_open)
                        : ! partsCatalogSelected()?.can_open"
                    title="{{ $selectedCatalog['open_label'] ?? 'Open parts catalog' }}"
                    :title="partsCatalogSelected()?.can_open ? partsCatalogSelected().open_label : (partsCatalogSelected()?.blocked_reason || '')"
                >
                    <span x-show="partstechCatalogOpening" x-cloak class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                    <span x-text="partsCatalogSelected()?.label">{{ $selectedCatalog['label'] ?? 'Parts catalog' }}</span>
                </button>
                @if ($partsCatalogSplit)
                    <button
                        type="button"
                        class="btn btn-sm dropdown-toggle dropdown-toggle-split"
                        :class="({{ $catalogColors }})[partsCatalogSelected()?.color] || 'btn-outline-azure'"
                        @click="partsCatalogMenu = ! partsCatalogMenu"
                        :aria-expanded="partsCatalogMenu ? 'true' : 'false'"
                        aria-haspopup="menu"
                        aria-label="PartsTech quote and catalog options"
                        title="Linked quote and catalog options"
                        data-parts-catalog-split
                    ></button>
                @endif
            </div>
            <div class="dropdown-menu bottom-100 start-0 mb-1" :class="{ show: partsCatalogMenu }" role="menu" @keydown.escape.stop="partsCatalogMenu = false" data-parts-catalog-menu>
                @if ($linkedQuotesAvailable)
                    <button
                        type="button"
                        class="dropdown-item"
                        role="menuitem"
                        data-linked-parts-quote-open
                        @click="partsCatalogMenu = false; window.dispatchEvent(new CustomEvent('ark:linked-parts-quote-open', { detail: { invokeEl: $event.currentTarget } }))"
                    >
                        {{ $linkedQuoteLinked ? 'Manage linked quote' : 'Link PartsTech quote' }}
                    </button>
                    <div class="dropdown-divider"></div>
                @endif
                <template x-for="item in partsCatalogItems" :key="'footer-catalog-' + item.key">
                    <div>
                        <button type="button" class="dropdown-item" role="menuitem" @click="activatePartsCatalogItem(item)" :aria-current="item.key === partsCatalogSelectedKey && item.mode === 'catalog' ? 'true' : 'false'">
                            <span x-text="item.mode === 'link' ? item.open_label : item.label"></span>
                            <span x-show="item.mode === 'catalog' && item.key === partsCatalogSelectedKey" class="ms-auto small text-secondary">Selected</span>
                        </button>
                        <button
                            type="button"
                            class="dropdown-item small text-secondary"
                            x-show="item.key !== partsCatalogDefaultKey && item.mode === 'catalog'"
                            @click="setEstimateToolbarDefault('parts_catalog', item.key)"
                        >Make default</button>
                    </div>
                </template>
            </div>
        </div>
    @elseif ($linkedQuotesAvailable)
        <div class="position-relative d-none d-md-inline-flex" data-toolbar-group="linked-quote">
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                data-linked-parts-quote-open
                data-parts-catalog-split
                @click="window.dispatchEvent(new CustomEvent('ark:linked-parts-quote-open', { detail: { invokeEl: $event.currentTarget } }))"
                title="{{ $linkedQuoteLinked ? 'Manage the linked PartsTech quote' : 'Link a PartsTech quote' }}"
            >
                {{ $linkedQuoteLinked ? 'Manage linked quote' : 'Link PartsTech quote' }}
            </button>
        </div>
    @endif

    @if ($laborGuides !== [])
        <div class="position-relative" data-toolbar-group="labor-guide" @click.outside="laborGuideMenu = false">
            <div class="btn-group" role="group" aria-label="Labor Guide">
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="({{ $guideColors }})[laborGuidePrimary()?.key] || 'btn-outline-secondary'"
                    :title="laborGuidePrimary()?.title || ''"
                    data-labor-guide-primary
                    @click="runLaborGuideItem(laborGuidePrimary())"
                >
                    <span x-text="laborGuidePrimary()?.label">{{ $primaryGuide['label'] ?? App\Ark\Operations\LaborGuides\LaborGuideIntent::label() }}</span>
                </button>
                <button
                    type="button"
                    class="btn btn-sm dropdown-toggle dropdown-toggle-split"
                    :class="({{ $guideColors }})[laborGuidePrimary()?.key] || 'btn-outline-secondary'"
                    x-show="laborGuideSecondary().length > 0"
                    x-cloak
                    @click="laborGuideMenu = ! laborGuideMenu"
                    :aria-expanded="laborGuideMenu ? 'true' : 'false'"
                    aria-haspopup="menu"
                    aria-label="Other labor guides"
                    title="Other labor guides"
                ></button>
            </div>
            <div class="dropdown-menu bottom-100 start-0 mb-1" :class="{ show: laborGuideMenu && laborGuideSecondary().length > 0 }" role="menu">
                <template x-for="item in laborGuideSecondary()" :key="'footer-guide-' + item.key">
                    <div>
                        <button type="button" class="dropdown-item" role="menuitem" :title="item.title" :disabled="! item.can_open" @click="runLaborGuideItem(item)">
                            <span x-text="item.label"></span>
                        </button>
                        <button type="button" class="dropdown-item small text-secondary" @click="setEstimateToolbarDefault('labor_guide', item.key)">Make default</button>
                    </div>
                </template>
            </div>
        </div>
    @endif
</div>

@if ($hasCart)
    <button
        type="button"
        class="btn btn-sm"
        :class="$store.partsCart.attention ? 'btn-primary' : 'btn-outline-primary'"
        @click="{{ $openCart }}"
        aria-label="PartsTech cart"
        title="Review and import the PartsTech cart for this RO"
        data-ro-footer-cart
    >
        <x-tabler.icon name="shopping-cart" />
        <span class="ark-ro-footer-label-narrow" x-text="$store.partsCart.attention ? 'Pull Cart' : 'Cart'">Cart</span>
        <span x-show="$store.partsCart.count > 0" x-cloak class="badge bg-red text-red-fg ms-1" x-text="$store.partsCart.count"></span>
    </button>
@endif

<div class="d-md-none" x-data="{ toolsOpen: false }" @keydown.escape.window="toolsOpen = false">
    <button type="button" class="btn btn-sm" @click="toolsOpen = true" aria-label="Tools" aria-haspopup="dialog" :aria-expanded="toolsOpen" data-ro-footer-tools-toggle>
        <x-tabler.icon name="tool" />
        <span class="ark-ro-footer-label">Tools</span>
    </button>
    <div x-show="toolsOpen" x-cloak>
        <div class="offcanvas-backdrop show" @click="toolsOpen = false"></div>
        {{-- Bootstrap opens every .offcanvas.show it finds on page load and locks the page's scrolling, so show is bound, never rendered. --}}
        <div
            class="offcanvas offcanvas-bottom h-auto ark-ro-footer-sheet"
            :class="{ show: toolsOpen }"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ro-footer-tools-title"
            data-ro-footer-tools-sheet
        >
            <div class="offcanvas-header py-2">
                <h2 class="offcanvas-title h3" id="ro-footer-tools-title">Tools</h2>
                <button type="button" class="btn-close" aria-label="Close" @click="toolsOpen = false"></button>
            </div>
            <div class="list-group list-group-flush border-top">
                <button
                    type="button"
                    class="list-group-item list-group-item-action"
                    data-ro-footer-dealer-quote
                    @click="toolsOpen = false; window.dispatchEvent(new CustomEvent('ark:dealer-quote-capture-open'))"
                >Import Dealer Quote</button>
                @if ($linkedQuotesAvailable)
                    <button
                        type="button"
                        class="list-group-item list-group-item-action"
                        data-linked-parts-quote-open
                        @click="toolsOpen = false; window.dispatchEvent(new CustomEvent('ark:linked-parts-quote-open', { detail: { invokeEl: $event.currentTarget } }))"
                    >{{ $linkedQuoteLinked ? 'Manage linked quote' : 'Link PartsTech quote' }}</button>
                @endif
                <template x-for="item in partsCatalogItems" :key="'sheet-catalog-' + item.key">
                    <button
                        type="button"
                        class="list-group-item list-group-item-action"
                        :disabled="! item.can_open"
                        @click="toolsOpen = false; openPartsCatalog(item.key)"
                        x-text="item.open_label"
                    ></button>
                </template>
                <template x-for="item in laborGuideItems" :key="'sheet-guide-' + item.key">
                    <button
                        type="button"
                        class="list-group-item list-group-item-action"
                        @click="toolsOpen = false; runLaborGuideItem(item)"
                        x-text="item.label"
                    ></button>
                </template>
                @foreach ($presentActions as $action)
                    @if ($action->opensModal)
                        <button
                            type="button"
                            class="list-group-item list-group-item-action"
                            @click="toolsOpen = false; window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: '{{ $action->modalTask }}', invokeEl: $event.currentTarget } }))"
                        >{{ $action->label }}</button>
                    @else
                        <a
                            href="{{ $action->href }}"
                            @if ($action->opensInNewTab) target="_blank" rel="noopener" @endif
                            class="list-group-item list-group-item-action"
                            @click="toolsOpen = false"
                        >{{ $action->label }}</a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
