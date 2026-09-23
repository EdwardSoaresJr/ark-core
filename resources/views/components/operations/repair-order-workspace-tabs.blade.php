@props([
    'workspaceMode' => 'review',
    'repairOrder',
    'totals',
    'estimateVersion',
    'isTerminal' => false,
    'partsBlockingCount' => 0,
    'partsReadinessCounts' => null,
    'approvedConcerns' => null,
    'priorVehicleFutureWorkCount' => 0,
    'recordedFindingCount' => 0,
    'recommendationOpenCount' => 0,
    'recommendationSafetyCount' => 0,
])

@php
    $approvedConcerns = $approvedConcerns ?? collect();
    $partsReadinessCounts = $partsReadinessCounts ?? [
        'needs_ordered' => 0,
        'sourcing' => 0,
        'ordered' => 0,
        'partial' => 0,
        'backordered' => 0,
        'received' => 0,
        'installed' => 0,
    ];
    $workspaceTabs = $workspaceMode === 'review'
        ? ['builder', 'inspect', 'recommendations', 'comms', 'history', 'financial']
        : ['builder'];
    $lazyTabs = array_values(array_filter(
        $workspaceTabs,
        fn (string $tab): bool => $tab !== 'builder',
    ));
    $workspaceDefaultTab = 'builder';
    $showTabNav = count($workspaceTabs) > 1;
    $builderLabel = $workspaceMode === 'builder' ? 'Builder' : 'Estimate';
    $workspaceTabUrl = url('/app/repair-orders/'.$repairOrder->repair_order_id.'/workspace-tabs');
@endphp

<div
    id="repair-order-workspace-tabs"
    {{ $attributes->class([
        'ops-ro-workspace-tabs ops-review-panel min-w-0 scroll-mt-6',
        'ops-ro-workspace-tabs--builder-only' => ! $showTabNav,
    ]) }}
    x-data="arkRoWorkspaceTabs({
        defaultTab: @js($workspaceDefaultTab),
        storageKey: @js('ark:ro-workspace-tab:'.$workspaceMode.':'.$repairOrder->repair_order_id),
        tabs: @js($workspaceTabs),
        lazyTabs: @js($lazyTabs),
        tabUrl: @js($workspaceTabUrl),
        workspaceMode: @js($workspaceMode),
    })"
>
    @if ($showTabNav)
    <nav class="ops-ro-workspace-tabs__nav" aria-label="Repair order workspace" role="tablist">
        <button
            type="button"
            role="tab"
            class="ops-ro-workspace-tab"
            :class="tabClass('builder')"
            x-on:click="selectTab('builder'); $dispatch('ark-estimate-home')"
            :aria-selected="tab === 'builder'"
        >
            {{ $builderLabel }}
        </button>
        @if (in_array('inspect', $workspaceTabs, true))
            <button
                type="button"
                role="tab"
                class="ops-ro-workspace-tab"
                :class="tabClass('inspect')"
                x-on:click="selectTab('inspect')"
                :aria-selected="tab === 'inspect'"
            >
                Inspection
                @if ($recordedFindingCount > 0)
                    <span class="ops-ro-workspace-tab__meta ops-ro-workspace-tab__meta--findings">{{ $recordedFindingCount }}</span>
                @endif
            </button>
        @endif
        @if (in_array('recommendations', $workspaceTabs, true))
            <button
                type="button"
                role="tab"
                class="ops-ro-workspace-tab"
                :class="tabClass('recommendations')"
                x-on:click="selectTab('recommendations')"
                :aria-selected="tab === 'recommendations'"
            >
                Recommendations
                @if ($recommendationOpenCount > 0)
                    <span @class([
                        'ops-ro-workspace-tab__meta',
                        'ops-ro-workspace-tab__meta--safety' => $recommendationSafetyCount > 0,
                    ])>{{ $recommendationOpenCount }}</span>
                @endif
            </button>
        @endif
        @if (in_array('comms', $workspaceTabs, true))
            <button
                type="button"
                role="tab"
                class="ops-ro-workspace-tab"
                :class="tabClass('comms')"
                x-on:click="selectTab('comms')"
                :aria-selected="tab === 'comms'"
            >
                Communications
            </button>
        @endif
        @if (in_array('financial', $workspaceTabs, true))
            <button
                type="button"
                role="tab"
                class="ops-ro-workspace-tab"
                :class="tabClass('financial')"
                x-on:click="selectTab('financial')"
                :aria-selected="tab === 'financial'"
            >
                Financial
            </button>
        @endif
        @if (in_array('history', $workspaceTabs, true))
            <button
                type="button"
                role="tab"
                class="ops-ro-workspace-tab"
                :class="tabClass('history')"
                x-on:click="selectTab('history')"
                :aria-selected="tab === 'history'"
            >
                History
                @if ($priorVehicleFutureWorkCount > 0)
                    <span class="ops-ro-workspace-tab__meta">{{ $priorVehicleFutureWorkCount }}</span>
                @endif
            </button>
        @endif
    </nav>
    @endif

    @isset($header)
        {{ $header }}
    @endisset

    <div class="ops-estimate-layout">
        <div class="ops-ro-workspace-tabs__panels ops-estimate-main min-w-0">
            <div x-show="tab === 'builder'" role="tabpanel">
                {{ $slot }}
            </div>

            @foreach ($lazyTabs as $lazyTab)
                <div x-show="tab === '{{ $lazyTab }}'" :class="panelShellClass('{{ $lazyTab }}')" role="tabpanel">
                    <p x-show="tabErrors['{{ $lazyTab }}']" x-cloak class="px-3 py-3 text-xs font-semibold text-rose-700">Could not load this tab. Try selecting it again.</p>
                    <p x-show="tabLoading['{{ $lazyTab }}']" x-cloak class="px-3 py-3 text-xs font-semibold text-slate-500">Loading…</p>
                    <div data-workspace-tab-panel="{{ $lazyTab }}"></div>
                </div>
            @endforeach
        </div>

        @isset($rail)
            {{ $rail }}
        @endisset
    </div>
</div>
