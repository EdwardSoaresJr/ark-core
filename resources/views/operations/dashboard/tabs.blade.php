@php
    $canViewScoreboard = \App\Ark\Operations\Scoreboard\ShopOperatingScoreboardAccess::allows(auth()->user());
    $dashboardTab = request()->routeIs('operations.owner.scoreboard') ? 'scoreboard' : 'today';
    $todayUrl = request()->routeIs('operations.today')
        ? route('operations.today')
        : route('operations.dashboard');
    $dashboardTabs = [
        ['key' => 'today', 'label' => 'Today', 'url' => $todayUrl],
    ];

    if ($canViewScoreboard) {
        $dashboardTabs[] = ['key' => 'scoreboard', 'label' => 'Scoreboard', 'url' => route('operations.owner.scoreboard')];
    }
@endphp

@if (count($dashboardTabs) > 1)
    <div class="ops-ro-workspace-tabs min-w-0 max-w-full">
        <nav class="ops-ro-workspace-tabs__nav" role="tablist" aria-label="Dashboard">
            @foreach ($dashboardTabs as $tab)
                <a href="{{ $tab['url'] }}" role="tab" class="ops-ro-workspace-tab{{ $dashboardTab === $tab['key'] ? ' ops-ro-workspace-tab--active' : '' }}" aria-selected="{{ $dashboardTab === $tab['key'] ? 'true' : 'false' }}"@if ($dashboardTab === $tab['key']) aria-current="page"@endif>{{ $tab['label'] }}</a>
            @endforeach
        </nav>
    </div>
@endif
