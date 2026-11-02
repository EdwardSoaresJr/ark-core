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
<nav class="navbar-nav flex-row flex-nowrap" aria-label="Communications by channel" data-ark-channel-strip>
    @foreach ($comms_channel_tabs as $tab)
        <div class="nav-item">
            <a href="{{ $tab['url'] }}" class="nav-link px-2 text-nowrap">
                {{ $tab['label'] }}
                <span @class([
                    'badge ms-2',
                    'bg-red text-red-fg' => $tab['count'] > 0,
                    'bg-secondary-lt' => $tab['count'] === 0,
                ])>{{ $tab['count'] }}@if ($tab['count'] > 0)<span class="visually-hidden"> need attention</span>@endif</span>
            </a>
        </div>
    @endforeach
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
