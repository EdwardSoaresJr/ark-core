@php
    /** @var list<array<string, mixed>> $items */
    /** @var array<string, mixed>|null $selected */
    $section = $section ?? null;
    $listFilter = $listFilter ?? request()->string('filter')->toString() ?: 'needs';
    $filterCounts = $filterCounts ?? null;
    $selection = array_filter([
        'conversation' => request()->integer('conversation') ?: null,
        'lead' => request()->integer('lead') ?: null,
        'call' => request()->integer('call') ?: null,
    ]);

    $filterLinks = [
        ['key' => 'all', 'label' => 'All', 'count' => $filterCounts['all'] ?? null],
        ['key' => 'needs', 'label' => 'Needs attention', 'count' => $filterCounts['needs'] ?? null],
        ['key' => 'waiting', 'label' => 'Waiting', 'count' => $filterCounts['waiting'] ?? null],
        ['key' => 'resolved', 'label' => 'Resolved', 'count' => $filterCounts['resolved'] ?? null],
    ];
@endphp

<div class="ops-comms-workspace__panel">
    <div class="ops-comms-workspace__panel-header">
        <h3 class="ops-comms-workspace__panel-title">{{ $title }}</h3>
        @if ($count > 0)
            <x-operations.pressure-count :count="$count" inline />
        @endif
    </div>

    @if ($section === 'inbox')
        <nav class="ops-comms-workspace__list-filters" aria-label="Inbox filters">
            @foreach ($filterLinks as $filterLink)
                <a
                    href="{{ route('operations.communications.inbox', array_merge($selection, ['filter' => $filterLink['key']])) }}"
                    @class([
                        'ops-comms-workspace__list-filter',
                        'ops-comms-workspace__list-filter--active' => $listFilter === $filterLink['key'],
                    ])
                >
                    <span>{{ $filterLink['label'] }}</span>
                    @if (($filterLink['count'] ?? null) !== null)
                        <span class="ops-comms-workspace__list-filter-count">{{ $filterLink['count'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    @endif

    @if ($items === [])
        <p class="ops-comms-workspace__empty">Nothing here yet.</p>
    @else
        <ul class="ops-comms-workspace__list-items">
            @foreach ($items as $item)
                @php
                    $isSelected = filled($selected['key'] ?? null)
                        && ($selected['key'] ?? null) === ($item['key'] ?? null);
                    $isChannelList = filled($item['url'] ?? null) && ! filled($item['select_url'] ?? null);
                    if ($isChannelList && request()->routeIs('operations.communications.internal.channel')) {
                        $isSelected = request()->route('channel')?->slug === ($item['slug'] ?? null);
                    }
                @endphp
                <li>
                    @include('operations.communications.workspace.partials.list-row', [
                        'item' => $item,
                        'isSelected' => $isSelected,
                    ])
                </li>
            @endforeach
        </ul>
    @endif
</div>
