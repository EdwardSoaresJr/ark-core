@php
    use App\Ark\Operations\LaborGuides\LaborGuideIntent;

    $laborGuides = $laborGuides ?? [];
    $laborGuideDefault = $laborGuideDefault ?? null;
    $primaryGuide = collect($laborGuides)->firstWhere('key', $laborGuideDefault) ?? ($laborGuides[0] ?? null);
    $concernId = $concernId ?? null;
@endphp

@if ($laborGuides !== [])
    <div
        class="relative"
        data-toolbar-group="labor-guide"
        x-data="{ guideMenu: false }"
        @click.outside="guideMenu = false"
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
                @click="@if ($concernId) rteLabor.concernId = {{ (int) $concernId }}; @endif runLaborGuideItem(laborGuidePrimary()); guideMenu = false"
            >
                <span x-text="laborGuidePrimary()?.label">{{ $primaryGuide['label'] ?? LaborGuideIntent::label() }}</span>
            </button>
            <button
                type="button"
                class="ops-review-action ops-review-action--labor-guide ops-review-action-group__segment ops-review-action-group__segment--secondary"
                x-show="laborGuideSecondary().length > 0"
                x-cloak
                @click="guideMenu = ! guideMenu"
                :aria-expanded="guideMenu ? 'true' : 'false'"
                aria-haspopup="menu"
                title="Other labor guides"
            >
                ▾
            </button>
        </div>
        <div
            x-show="guideMenu && laborGuideSecondary().length > 0"
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
                        @click="@if ($concernId) rteLabor.concernId = {{ (int) $concernId }}; @endif runLaborGuideItem(item); guideMenu = false"
                        role="menuitem"
                    >
                        <span x-text="item.label"></span>
                    </button>
                    <button
                        type="button"
                        class="block w-full px-3 pb-1.5 text-left text-[11px] font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800"
                        title="Show this on the main button"
                        @click="setEstimateToolbarDefault('labor_guide', item.key); guideMenu = false"
                    >
                        Make default
                    </button>
                </div>
            </template>
        </div>
    </div>
@endif
