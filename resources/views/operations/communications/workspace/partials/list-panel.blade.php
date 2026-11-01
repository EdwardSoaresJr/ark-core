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

<div class="d-flex flex-column flex-fill overflow-hidden" data-comms-list-panel>
    @if ($section === 'inbox')
        <nav class="p-2 border-bottom" aria-label="Advisor filter">
            <div class="btn-group w-100">
                @foreach ([
                    ['key' => 'everyone', 'label' => 'Everyone'],
                    ['key' => 'mine', 'label' => 'Mine'],
                    ['key' => 'unassigned', 'label' => 'Unassigned'],
                ] as $ownerLink)
                    <a
                        href="{{ route('operations.communications.inbox', array_merge($selection, ['owner' => $ownerLink['key']])) }}"
                        @class(['btn btn-sm', 'active' => $ownerFilter === $ownerLink['key']])
                        @if ($ownerFilter === $ownerLink['key']) aria-current="page" @endif
                    >
                        {{ $ownerLink['label'] }}
                        <span class="text-secondary ms-1">{{ $ownerCounts[$ownerLink['key']] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    @else
        <div class="card-header py-2">
            <h3 class="card-title">{{ $title }}</h3>
            @if ($count > 0)
                <span class="badge bg-red-lt ms-2">{{ $count }}</span>
            @endif
        </div>
    @endif

    @if ($items === [])
        <div class="card-body text-secondary">Nothing here yet.</div>
    @else
        <div class="list-group list-group-flush overflow-auto flex-fill" data-comms-list-items>
            @foreach ($items as $item)
                @include('operations.communications.workspace.partials.list-row', [
                    'item' => $item,
                    'isSelected' => filled($selected['key'] ?? null) && ($selected['key'] ?? null) === ($item['key'] ?? null),
                ])
            @endforeach
        </div>
    @endif
</div>
