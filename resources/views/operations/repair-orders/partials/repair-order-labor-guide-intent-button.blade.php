@php
    use App\Ark\Operations\LaborGuides\LaborGuideIntent;

    $laborGuides = $laborGuides ?? [];
    $laborGuideDefault = $laborGuideDefault ?? null;
    $primaryGuide = collect($laborGuides)->firstWhere('key', $laborGuideDefault) ?? ($laborGuides[0] ?? null);
@endphp

@if ($laborGuides !== [])
    <div
        class="relative"
        data-toolbar-group="labor-guide"
        @click.outside="laborGuideMenu = false"
    >
        <div class="ops-review-action-group" role="group" :aria-label="laborGuidePrimary()?.label || 'Labor Guide'">
            <button
                type="button"
                class="ops-review-action ops-review-action--labor-guide ops-review-action-group__segment"
                :class="{
                    'ops-review-action--labor-guide-rte': laborGuidePrimary()?.key === 'rte',
                    'ops-review-action--labor-guide-alldata': laborGuidePrimary()?.key === 'alldata',
                    'ops-review-action--labor-guide-prodemand': laborGuidePrimary()?.key === 'prodemand',
                    'opacity-60': Boolean(laborGuidePrimary()?.blocked_reason) && laborGuidePrimary()?.kind === 'rte',
                }"
                :title="laborGuidePrimary()?.title || ''"
                @click="runLaborGuideItem(laborGuidePrimary())"
            >
                <span x-text="laborGuidePrimary()?.label">{{ $primaryGuide['label'] ?? LaborGuideIntent::label() }}</span>
            </button>
            <button
                type="button"
                class="ops-review-action ops-review-action--labor-guide ops-review-action-group__segment ops-review-action-group__segment--secondary"
                x-show="laborGuideSecondary().length > 0"
                x-cloak
                @click="laborGuideMenu = ! laborGuideMenu"
                :aria-expanded="laborGuideMenu ? 'true' : 'false'"
                aria-haspopup="menu"
                title="Other labor guides"
            >
                <span class="ark-tabler-ro__split-caret" aria-hidden="true"></span>
            </button>
        </div>
        <div
            x-show="laborGuideMenu && laborGuideSecondary().length > 0"
            x-cloak
            class="absolute left-0 z-20 mt-1 min-w-[12rem] border border-slate-200 bg-white py-1 shadow-sm"
            role="menu"
        >
            <template x-for="item in laborGuideSecondary()" :key="item.key">
                <div class="border-t border-slate-100 first:border-t-0">
                    <button
                        type="button"
                        class="block w-full px-3 py-1.5 text-left text-xs font-semibold text-slate-800 hover:bg-slate-50"
                        :title="item.title"
                        :disabled="! item.can_open"
                        @click="runLaborGuideItem(item)"
                        role="menuitem"
                    >
                        <span x-text="item.label"></span>
                    </button>
                    <button
                        type="button"
                        class="block w-full px-3 pb-1.5 text-left text-[11px] font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800"
                        title="Show this on the main button"
                        @click="setEstimateToolbarDefault('labor_guide', item.key)"
                    >
                        Make default
                    </button>
                </div>
            </template>
        </div>
    </div>
@endif
