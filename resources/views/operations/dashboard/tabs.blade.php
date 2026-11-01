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
    <nav class="nav nav-pills mb-3" role="tablist" aria-label="Dashboard">
        @foreach ($dashboardTabs as $tab)
            <a href="{{ $tab['url'] }}" role="tab" @class(['nav-link', 'active' => $dashboardTab === $tab['key']]) aria-selected="{{ $dashboardTab === $tab['key'] ? 'true' : 'false' }}"@if ($dashboardTab === $tab['key']) aria-current="page"@endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>
@endif
