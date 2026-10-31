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
        ? ['builder', 'inspect', 'recommendations', 'comms', 'history']
        : ['builder'];
    $lazyTabs = array_values(array_filter(
        $workspaceTabs,
        fn (string $tab): bool => $tab !== 'builder',
    ));
    $workspaceDefaultTab = 'builder';
    $showTabNav = count($workspaceTabs) > 1;
    $tabLabels = [
        'builder' => $workspaceMode === 'builder' ? 'Builder' : 'Estimate',
        'inspect' => 'Inspection',
        'recommendations' => 'Recommendations',
        'comms' => 'Communications',
        'history' => 'History',
    ];
    $tabCounts = [
        'inspect' => $recordedFindingCount > 0
            ? ['count' => $recordedFindingCount, 'meta' => 'findings', 'tone' => 'bg-azure-lt']
            : null,
        'recommendations' => $recommendationOpenCount > 0
            ? [
                'count' => $recommendationOpenCount,
                'meta' => $recommendationSafetyCount > 0 ? 'safety' : 'open',
                'tone' => $recommendationSafetyCount > 0 ? 'bg-red-lt' : 'bg-secondary-lt',
            ]
            : null,
        'history' => $priorVehicleFutureWorkCount > 0
            ? ['count' => $priorVehicleFutureWorkCount, 'meta' => 'future-work', 'tone' => 'bg-secondary-lt']
            : null,
    ];
    $workspaceTabUrl = url('/app/repair-orders/'.$repairOrder->repair_order_id.'/workspace-tabs');
@endphp

<div
    id="repair-order-workspace-tabs"
    {{ $attributes->class([
        'card ops-ro-workspace-tabs min-w-0 scroll-mt-6',
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
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist" aria-label="Repair order workspace">
                @foreach ($workspaceTabs as $workspaceTab)
                    <li class="nav-item" role="presentation">
                        <button
                            type="button"
                            role="tab"
                            class="nav-link"
                            data-workspace-tab="{{ $workspaceTab }}"
                            :class="{ active: tab === @js($workspaceTab) }"
                            x-on:click="selectTab(@js($workspaceTab))"
                            :aria-selected="tab === @js($workspaceTab)"
                        >
                            {{ $tabLabels[$workspaceTab] }}
                            @if ($tabCounts[$workspaceTab] ?? null)
                                <span class="badge {{ $tabCounts[$workspaceTab]['tone'] }} ms-2" data-workspace-tab-count="{{ $tabCounts[$workspaceTab]['meta'] }}">{{ $tabCounts[$workspaceTab]['count'] }}</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card-body">
        <div x-show="tab === 'builder'" role="tabpanel">
            <x-operations.worksheet-island>
                {{ $slot }}
            </x-operations.worksheet-island>
        </div>

        @foreach ($lazyTabs as $lazyTab)
            <div x-show="tab === '{{ $lazyTab }}'" x-cloak role="tabpanel">
                <div x-show="tabErrors['{{ $lazyTab }}']" x-cloak class="alert alert-danger">Could not load this tab. Try selecting it again.</div>
                <p x-show="tabLoading['{{ $lazyTab }}']" x-cloak class="text-secondary">
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading…
                </p>
                <x-operations.worksheet-island>
                    <div data-workspace-tab-panel="{{ $lazyTab }}"></div>
                </x-operations.worksheet-island>
            </div>
        @endforeach
    </div>
</div>
