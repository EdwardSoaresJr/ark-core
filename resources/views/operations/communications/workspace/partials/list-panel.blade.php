@php
    $section = $section ?? null;
    $listFilter = $listFilter ?? 'needs';
    $ownerFilter = $ownerFilter ?? 'everyone';
    $ownerCounts = is_array($ownerCounts ?? null) ? $ownerCounts : ['everyone' => $count, 'mine' => 0, 'unassigned' => 0];
    $selection = array_filter([
        'conversation' => request()->integer('conversation') ?: null,
        'lead' => request()->integer('lead') ?: null,
        'call' => request()->integer('call') ?: null,
        'platform_conversation' => request()->string('platform_conversation')->toString() ?: null,
        'filter' => $listFilter,
    ]);
@endphp

<div class="ops-comms-workspace__panel">
    @if ($section === 'inbox')
        <nav class="ops-comms-inbox__owners" aria-label="Advisor filter">
            @foreach ([
                ['key' => 'everyone', 'label' => 'Everyone'],
                ['key' => 'mine', 'label' => 'Mine'],
                ['key' => 'unassigned', 'label' => 'Unassigned'],
            ] as $ownerLink)
                <a
                    href="{{ route('operations.communications.inbox', array_merge($selection, ['owner' => $ownerLink['key']])) }}"
                    @class(['ops-comms-inbox__owner', 'ops-comms-inbox__owner--active' => $ownerFilter === $ownerLink['key']])
                >
                    {{ $ownerLink['label'] }}
                    <span class="ops-comms-inbox__owner-count">({{ $ownerCounts[$ownerLink['key']] ?? 0 }})</span>
                </a>
            @endforeach
        </nav>
    @else
        <div class="ops-comms-workspace__panel-header">
            <h3 class="ops-comms-workspace__panel-title">{{ $title }}</h3>
            @if ($count > 0)
                <x-operations.pressure-count :count="$count" inline />
            @endif
        </div>
    @endif

    @if ($items === [])
        <p class="ops-comms-workspace__empty ops-comms-workspace__empty-fill">Nothing here yet.</p>
    @else
        <ul class="ops-comms-workspace__list-items">
            @foreach ($items as $item)
                <li>
                    @include('operations.communications.workspace.partials.list-row', [
                        'item' => $item,
                        'isSelected' => filled($selected['key'] ?? null) && ($selected['key'] ?? null) === ($item['key'] ?? null),
                    ])
                </li>
            @endforeach
        </ul>
    @endif
</div>
