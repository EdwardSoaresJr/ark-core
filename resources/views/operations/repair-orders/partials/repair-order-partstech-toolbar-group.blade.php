@php
    $partsCatalogs = $partsCatalogs ?? [];
    $partsCatalogDefault = $partsCatalogDefault ?? null;
    $catalogButtons = collect($partsCatalogs)->where('mode', 'catalog')->values();
    $selectedCatalog = $catalogButtons->firstWhere('key', $partsCatalogDefault)
        ?? $catalogButtons->first()
        ?? collect($partsCatalogs)->firstWhere('key', $partsCatalogDefault)
        ?? ($partsCatalogs[0] ?? null);
@endphp

@if ($partsCatalogs !== [])
    <div
        class="relative"
        data-toolbar-group="parts-catalog"
        x-data="{ catalogMenu: false }"
        @click.outside="catalogMenu = false"
    >
        <div class="ops-review-action-group ops-review-action-group--procurement" role="group" aria-label="Parts catalog">
            <button
                type="button"
                class="ops-review-action ops-review-action-group__segment"
                :class="partsCatalogSelected()?.button_class || 'ops-review-action--procurement'"
                :data-parts-catalog="partsCatalogSelected()?.key"
                data-parts-catalog="{{ $selectedCatalog['key'] ?? '' }}"
                @click="openPartsCatalog(partsCatalogSelected()?.key, false, {{ (int) ($concernId ?? 0) }} || null)"
                :disabled="partsCatalogSelected()?.kind === 'partstech' && partsCatalogSelected()?.mode === 'catalog'
                    ? (partstechCatalogOpening || partstechPullLoading || ! partsCatalogSelected()?.can_open)
                    : ! partsCatalogSelected()?.can_open"
                :aria-label="partsCatalogSelected()?.open_label || 'Open parts catalog'"
                :title="partsCatalogSelected()?.can_open
                    ? (partsCatalogSelected().open_label + (partsCatalogSelected().po_number ? ' for RO ' + partsCatalogSelected().po_number : ''))
                    : (partsCatalogSelected()?.blocked_reason || '')"
                title="{{ $selectedCatalog['open_label'] ?? 'Open' }}"
            >
                <span
                    x-show="! (partsCatalogSelected()?.kind === 'partstech' && partsCatalogSelected()?.mode === 'catalog' && partstechCatalogOpening)"
                    x-cloak
                    x-text="partsCatalogSelected()?.label"
                >{{ $selectedCatalog['label'] ?? 'Parts catalog' }}</span>
                <span
                    x-show="partsCatalogSelected()?.kind === 'partstech' && partsCatalogSelected()?.mode === 'catalog' && partstechCatalogOpening"
                    x-cloak
                    class="inline-flex items-center gap-1.5"
                >
                    <span class="ops-partstech-loader ops-partstech-loader--inline" aria-hidden="true"></span>
                    Opening…
                </span>
            </button>
            <button
                type="button"
                class="ops-review-action ops-review-action-group__segment ops-review-action-group__segment--secondary"
                :class="partsCatalogSelected()?.button_class || 'ops-review-action--procurement'"
                x-show="partsCatalogItems.length > 1"
                x-cloak
                @click="catalogMenu = ! catalogMenu"
                @keydown.arrow-down.prevent="catalogMenu = true"
                @keydown.arrow-up.prevent="catalogMenu = true"
                :aria-expanded="catalogMenu ? 'true' : 'false'"
                aria-haspopup="menu"
                aria-controls="parts-catalog-selector-{{ (int) ($concernId ?? 0) }}"
                :aria-label="'Choose parts catalog, ' + (partsCatalogSelected()?.label || 'none')"
                title="Choose a catalog or open a parts link"
            >
                ▾
            </button>
            <button
                type="button"
                class="ops-review-action ops-review-action-group__segment ops-review-action-group__segment--secondary"
                :class="partsCatalogSelected()?.button_class || 'ops-review-action--procurement'"
                x-show="partsCatalogSelected()?.can_pull_quote"
                x-cloak
                :disabled="partstechPullLoading || partstechCatalogOpening"
                @click="pullPartsCatalogQuote(partsCatalogSelected()?.key)"
                :title="partsCatalogSelected()?.cart_label || ''"
            >
                <span x-show="! partstechPullLoading" x-cloak x-text="partsCatalogSelected()?.cart_label">{{ $selectedCatalog['cart_label'] ?? '' }}</span>
                <span x-show="partstechPullLoading" x-cloak class="inline-flex items-center gap-1.5">
                    <span class="ops-partstech-loader ops-partstech-loader--inline" aria-hidden="true"></span>
                    <span x-text="partstechPullStatus || ('Pulling ' + (partsCatalogActionLabel || partsCatalogSelected()?.cart_label || 'cart') + '…')"></span>
                </span>
            </button>
        </div>
        <div
            id="parts-catalog-selector-{{ (int) ($concernId ?? 0) }}"
            x-show="catalogMenu"
            x-cloak
            class="absolute left-0 z-20 mt-1 min-w-[13rem] border border-slate-200 bg-white py-1 shadow-sm"
            role="menu"
            @keydown.escape.stop="catalogMenu = false"
        >
            <template x-for="item in partsCatalogItems" :key="item.key">
                <div class="border-t border-slate-100 first:border-t-0">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-semibold text-slate-800 hover:bg-slate-50"
                        :class="item.key === partsCatalogSelectedKey && item.mode === 'catalog' ? 'bg-slate-50' : ''"
                        :title="item.mode === 'link' ? ('Open ' + item.label + ' in a new tab') : ('Use ' + item.label + ' on this repair order')"
                        :aria-current="item.key === partsCatalogSelectedKey && item.mode === 'catalog' ? 'true' : 'false'"
                        @click="if (item.mode === 'link') { openPartsCatalog(item.key, false, {{ (int) ($concernId ?? 0) }} || null); } else { selectPartsCatalog(item.key); } catalogMenu = false"
                        role="menuitem"
                    >
                        <span class="inline-block size-2.5 shrink-0 rounded-sm" :class="item.swatch_class" aria-hidden="true"></span>
                        <span x-text="item.label"></span>
                        <span
                            x-show="item.mode === 'link'"
                            class="ml-auto text-[0.625rem] font-semibold uppercase tracking-wide text-slate-400"
                        >Link</span>
                        <span
                            x-show="item.mode === 'catalog' && item.key === partsCatalogSelectedKey"
                            class="ml-auto text-[0.625rem] font-semibold uppercase tracking-wide text-slate-500"
                        >Selected</span>
                    </button>
                    <button
                        type="button"
                        class="block w-full px-3 pb-1.5 text-left text-[11px] font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800"
                        x-show="item.key !== partsCatalogDefaultKey && (item.mode === 'catalog' || ! partsCatalogItems.some((entry) => entry.mode === 'catalog'))"
                        :aria-label="'Make ' + item.label + ' the default catalog'"
                        title="Save as this advisor's default catalog"
                        @click="setEstimateToolbarDefault('parts_catalog', item.key)"
                    >
                        Make default
                    </button>
                </div>
            </template>
        </div>
    </div>
@endif
