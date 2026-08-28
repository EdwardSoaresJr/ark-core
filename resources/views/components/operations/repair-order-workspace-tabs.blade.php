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
        ? ['builder', 'inspect', 'comms', 'portal', 'auth', 'parts', 'history']
        : ['builder'];
    $lazyTabs = array_values(array_filter(
        $workspaceTabs,
        fn (string $tab): bool => $tab !== 'builder',
    ));
    $workspaceDefaultTab = 'builder';
    $showTabNav = count($workspaceTabs) > 1;
    $authTabCents = $totals->totalCents();
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
    <nav class="ops-ro-workspace-tabs__nav" aria-label="Repair order workspace tabs">
        <button
            type="button"
            class="ops-ro-workspace-tab"
            :class="tabClass('builder')"
            x-on:click="selectTab('builder')"
            :aria-selected="tab === 'builder'"
        >
            {{ $builderLabel }}
        </button>
        @if (in_array('inspect', $workspaceTabs, true))
            <button
                type="button"
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
        @if (in_array('comms', $workspaceTabs, true))
            <button
                type="button"
                class="ops-ro-workspace-tab"
                :class="tabClass('comms')"
                x-on:click="selectTab('comms')"
                :aria-selected="tab === 'comms'"
            >
                Comms
            </button>
        @endif
        @if (in_array('portal', $workspaceTabs, true))
            <button
                type="button"
                class="ops-ro-workspace-tab"
                :class="tabClass('portal')"
                x-on:click="selectTab('portal')"
                :aria-selected="tab === 'portal'"
            >
                Portal
            </button>
        @endif
        @if (in_array('auth', $workspaceTabs, true))
            <button
                type="button"
                class="ops-ro-workspace-tab"
                :class="tabClass('auth')"
                x-on:click="selectTab('auth')"
                :aria-selected="tab === 'auth'"
            >
                Auth
                @if ($authTabCents > 0)
                    <span class="ops-ro-workspace-tab__meta">{{ $totals->format($authTabCents) }}</span>
                @endif
            </button>
        @endif
        @if (in_array('parts', $workspaceTabs, true))
            <button
                type="button"
                class="ops-ro-workspace-tab"
                :class="tabClass('parts')"
                x-on:click="selectTab('parts')"
                :aria-selected="tab === 'parts'"
            >
                Parts
                @if ($partsBlockingCount > 0)
                    <span class="ops-ro-workspace-tab__meta ops-ro-workspace-tab__meta--warn">{{ $partsBlockingCount }}</span>
                @endif
            </button>
        @endif
        @if (in_array('history', $workspaceTabs, true))
            <button
                type="button"
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

    <div class="ops-ro-workspace-tabs__panels">
        <div x-show="tab === 'builder'">
            {{ $slot }}
        </div>

        @foreach ($lazyTabs as $lazyTab)
            <div x-show="tab === '{{ $lazyTab }}'" :class="panelShellClass('{{ $lazyTab }}')">
                <p x-show="tabErrors['{{ $lazyTab }}']" x-cloak class="px-3 py-3 text-xs font-semibold text-rose-700">Could not load this tab. Try selecting it again.</p>
                <p x-show="tabLoading['{{ $lazyTab }}']" x-cloak class="px-3 py-3 text-xs font-semibold text-slate-500">Loading…</p>
                <div data-workspace-tab-panel="{{ $lazyTab }}"></div>
            </div>
        @endforeach
    </div>
</div>
