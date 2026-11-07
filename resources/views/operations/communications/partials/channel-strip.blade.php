@php
    /** @var list<array{slug: string, label: string, count: int, url: string, active: bool}> $comms_channel_tabs */
    $variant = $variant ?? 'band';
    $channelTones = [
        'all' => 'customer',
        'phone' => 'motion',
        'sms' => 'ready',
        'messenger' => 'motion',
        'email' => 'move',
        'portal' => 'approval',
    ];
@endphp

@if ($variant === 'tabler')
@php
    $attentionTotal = (int) (collect($comms_channel_tabs)->firstWhere('slug', 'all')['count'] ?? 0);
    $urgent = (bool) ($urgent ?? false);
@endphp
{{-- One Inbox menu at every width: channels with how many need attention; red only while a call is live. --}}
<nav class="navbar-nav flex-row" aria-label="Communications by channel" data-ark-channel-strip>
    <div class="nav-item dropdown">
        <a
            href="#"
            class="nav-link px-2 gap-2"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-label="Inbox, {{ $attentionTotal }} need attention"
            data-ark-inbox-menu
        >
            <x-tabler.icon name="messages" />
            <span class="d-none d-lg-inline">Inbox</span>
            @if ($attentionTotal > 0)
                <span @class([
                    'badge',
                    'bg-red text-red-fg' => $urgent,
                    'bg-secondary-lt' => ! $urgent,
                ]) data-ark-inbox-count>{{ $attentionTotal }}</span>
            @endif
        </a>
        <div class="dropdown-menu dropdown-menu-arrow">
            <span class="dropdown-header">Inbox · need attention</span>
            @foreach ($comms_channel_tabs as $tab)
                <a href="{{ $tab['url'] }}" class="dropdown-item" data-ark-inbox-channel="{{ $tab['slug'] }}">
                    @if (filled($tab['icon'] ?? null))
                        <x-tabler.icon :name="$tab['icon']" class="me-2 text-secondary" />
                    @endif
                    {{ $tab['filter_label'] ?? $tab['label'] }}
                    @if ($tab['count'] > 0)
                        <span class="badge bg-secondary-lt ms-auto">{{ $tab['count'] }}<span class="visually-hidden"> need attention</span></span>
                    @endif
                </a>
            @endforeach
            <div class="dropdown-divider"></div>
            <a href="#" class="dropdown-item" data-ops-global-search-open>
                <x-tabler.icon name="search" class="me-2 text-secondary" />
                Search conversations
            </a>
        </div>
    </div>
</nav>
@else
<section @class([
    'ops-home-band ops-home-band--comms' => $variant === 'band',
    'ops-topbar-comms' => $variant === 'topbar',
]) aria-label="Communications by channel">
    <h3 class="sr-only">Communications</h3>

    <div class="ops-comms-channel-strip">
        <div class="ops-comms-channel-strip-metrics">
            @foreach ($comms_channel_tabs as $tab)
                <a
                    href="{{ $tab['url'] }}"
                    @class([
                        'ops-board-snapshot-metric',
                        'ops-board-snapshot-metric--'.($channelTones[$tab['slug']] ?? 'move'),
                        'hover:bg-slate-50',
                        'ops-comms-channel-strip-metric--empty' => $tab['count'] === 0,
                    ])
                >
                    <div class="ops-board-snapshot-metric-head">
                        <p class="ops-board-snapshot-label">{{ $tab['label'] }}</p>
                        <p class="ops-board-snapshot-value {{ $tab['count'] > 0 ? 'ops-board-snapshot-value--active' : '' }}">{{ $tab['count'] }}</p>
                    </div>
                    <p class="ops-board-snapshot-hint">
                        @if ($variant === 'topbar')
                            @if ($tab['count'] > 0)
                                Attention
                            @else
                                -
                            @endif
                        @elseif ($tab['slug'] === 'all')
                            {{ $tab['count'] > 0 ? 'Needs attention' : 'Open queue' }}
                        @else
                            {{ $tab['count'] > 0 ? 'Needs attention' : 'Clear' }}
                        @endif
                    </p>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
