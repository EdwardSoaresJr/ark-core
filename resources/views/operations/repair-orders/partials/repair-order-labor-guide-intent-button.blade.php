@php
    use App\Ark\Operations\LaborGuides\LaborGuideIntent;

    $laborGuides = $laborGuides ?? [];
    $laborGuideDefault = $laborGuideDefault ?? null;
    $primaryGuide = collect($laborGuides)->firstWhere('key', $laborGuideDefault) ?? ($laborGuides[0] ?? null);
    // Each provider keeps its own outline color: RTE teal, ALLDATA yellow, ProDemand indigo.
    $guideColors = "{ rte: 'btn-outline-teal', alldata: 'btn-outline-yellow', prodemand: 'btn-outline-indigo' }";
@endphp

@if ($laborGuides !== [])
    <div
        class="position-relative"
        data-toolbar-group="labor-guide"
        @click.outside="laborGuideMenu = false"
    >
        <div class="btn-group" role="group" :aria-label="laborGuidePrimary()?.label || 'Labor Guide'">
            <button
                type="button"
                class="btn"
                :class="[
                    ({{ $guideColors }})[laborGuidePrimary()?.key] || 'btn-outline-secondary',
                    Boolean(laborGuidePrimary()?.blocked_reason) && laborGuidePrimary()?.kind === 'rte' ? 'opacity-50' : '',
                ]"
                :title="laborGuidePrimary()?.title || ''"
                data-labor-guide-primary
                @click="runLaborGuideItem(laborGuidePrimary())"
            >
                <span x-text="laborGuidePrimary()?.label">{{ $primaryGuide['label'] ?? LaborGuideIntent::label() }}</span>
            </button>
            <button
                type="button"
                class="btn btn-icon dropdown-toggle dropdown-toggle-split"
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
        <div
            class="dropdown-menu top-100 start-0 mt-1"
            :class="{ show: laborGuideMenu && laborGuideSecondary().length > 0 }"
            role="menu"
        >
            <template x-for="item in laborGuideSecondary()" :key="item.key">
                <div>
                    <button
                        type="button"
                        class="dropdown-item"
                        :title="item.title"
                        :disabled="! item.can_open"
                        @click="runLaborGuideItem(item)"
                        role="menuitem"
                    >
                        <span x-text="item.label"></span>
                    </button>
                    <button
                        type="button"
                        class="dropdown-item small text-secondary"
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
