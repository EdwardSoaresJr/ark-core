@php
    /** @var list<array{slug: string, label: string, count: int, url: string, active: bool}> $comms_channel_tabs */
    /** @var \App\Ark\Operations\Communications\CommunicationsSurfaceChannel $comms_channel */
@endphp

<nav
    class="ops-comms-channel-tabs"
    aria-label="Filter communications by channel"
>
    @foreach ($comms_channel_tabs as $tab)
        <a
            href="{{ $tab['url'] }}"
            @class([
                'ops-comms-channel-tab',
                'ops-comms-channel-tab--active' => $tab['active'],
                'ops-comms-channel-tab--empty' => $tab['count'] === 0 && ! $tab['active'],
            ])
            @if ($tab['active']) aria-current="page" @endif
        >
            <span>{{ $tab['label'] }}</span>
            <span class="ops-comms-channel-tab__count">{{ $tab['count'] }}</span>
        </a>
    @endforeach
</nav>
