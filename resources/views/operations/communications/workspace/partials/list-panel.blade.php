@php
    $section = $section ?? null;
    $listFilter = $listFilter ?? 'needs';
    $ownerFilter = $ownerFilter ?? 'everyone';
    $ownerCounts = is_array($ownerCounts ?? null) ? $ownerCounts : ['everyone' => $count, 'mine' => 0, 'unassigned' => 0];
    $activeChannel = collect(is_array($channelOptions ?? null) ? $channelOptions : [])->firstWhere('active', true);
    $channelNoun = $activeChannel['noun'] ?? null;
    $selection = array_filter([
        'conversation' => request()->integer('conversation') ?: null,
        'lead' => request()->integer('lead') ?: null,
        'call' => request()->integer('call') ?: null,
        'platform_conversation' => request()->string('platform_conversation')->toString() ?: null,
        'filter' => $listFilter,
        'channel' => $channelNoun !== null ? $activeChannel['value'] : null,
    ]);
    $shownTotal = $channelNoun === null ? (int) ($listTotal ?? count($items)) : count($items);
    $unreadCount = count(array_filter($items, fn (array $item): bool => ! empty($item['unread'])));
    $conversationNoun = ($channelNoun !== null ? $channelNoun.' ' : '')
        .($shownTotal === 1 ? 'conversation' : 'conversations');
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
                        <span class="text-secondary ms-1 d-lg-none d-xxl-inline">{{ $ownerCounts[$ownerLink['key']] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
            <div class="small text-secondary mt-2 px-1" data-comms-list-summary>
                <span data-comms-list-total>{{ $shownTotal }} {{ $conversationNoun }}</span>@if ($unreadCount > 0)<span aria-hidden="true"> · </span><span class="text-primary fw-medium" data-comms-list-unread>{{ $unreadCount }} unread</span>@endif
            </div>
        </nav>
    @else
        <div class="card-header py-2">
            <h3 class="card-title">{{ $title }}</h3>
            @if ($count > 0)
                <span class="badge bg-secondary-lt ms-2">{{ $count }}</span>
            @endif
        </div>
    @endif

    @if ($items === [])
        <div class="card-body text-secondary">{{ $channelNoun !== null ? 'No '.$channelNoun.' conversations here.' : 'Nothing here yet.' }}</div>
    @else
        <div class="list-group list-group-flush overflow-auto flex-fill" data-comms-list-items>
            @foreach ($items as $item)
                @include('operations.communications.workspace.partials.list-row', [
                    'item' => $item,
                    'isSelected' => filled($selected['key'] ?? null) && ($selected['key'] ?? null) === ($item['key'] ?? null),
                    'listFilter' => $section === 'inbox' ? $listFilter : null,
                ])
            @endforeach
            @if ($section === 'inbox' && ($listTruncated ?? false) && filled($listNext ?? null) && (int) $listNext > count($items))
                <a
                    href="{{ route('operations.communications.inbox', array_merge($selection, ['owner' => $ownerFilter, 'shown' => (int) $listNext])) }}"
                    class="list-group-item list-group-item-action text-center text-secondary small"
                    data-comms-list-more
                    data-shown="{{ (int) $listNext }}"
                >
                    Show more @if ($listTotal !== null)<span class="ms-1">{{ count($items) }} of {{ (int) $listTotal }}</span>@endif
                </a>
            @endif
        </div>
    @endif
</div>
