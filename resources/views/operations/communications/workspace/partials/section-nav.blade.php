@php
    use App\Ark\Runtime\Authorization\ArkCapability;

    $listFilter = $listFilter ?? request()->string('filter')->toString() ?: 'needs';
    $ownerFilter = $ownerFilter ?? 'everyone';
    $filterCounts = is_array($filterCounts ?? null) ? $filterCounts : [];
    $selection = array_filter([
        'conversation' => request()->integer('conversation') ?: null,
        'lead' => request()->integer('lead') ?: null,
        'call' => request()->integer('call') ?: null,
        'platform_conversation' => request()->string('platform_conversation')->toString() ?: null,
        'owner' => $ownerFilter !== 'everyone' ? $ownerFilter : null,
    ]);
    $lanes = [
        [
            'key' => 'needs',
            'label' => 'Needs attention',
            'title' => 'Shop action is due now - reply, overdue follow-up, or unresolved call',
            'count' => $filterCounts['needs'] ?? null,
        ],
        [
            'key' => 'waiting',
            'label' => 'Waiting',
            'title' => 'Open - awaiting a customer reply or a follow-up that is not due yet',
            'count' => $filterCounts['waiting'] ?? null,
        ],
        [
            'key' => 'resolved',
            'label' => 'Resolved',
            'title' => 'Finished communication work',
            'count' => $filterCounts['resolved'] ?? null,
        ],
    ];
@endphp

<nav class="flex-shrink-0 mb-2 overflow-auto" aria-label="Communications sections" data-comms-section-nav>
    <ul class="nav nav-pills flex-nowrap">
        @if ($section === 'inbox')
            @foreach ($lanes as $lane)
                <li class="nav-item">
                    <a
                        href="{{ route('operations.communications.inbox', array_merge($selection, ['filter' => $lane['key']])) }}"
                        title="{{ $lane['title'] }}"
                        @class(['nav-link text-nowrap', 'active' => $listFilter === $lane['key']])
                        @if ($listFilter === $lane['key']) aria-current="page" @endif
                    >
                        <span>{{ $lane['label'] }}</span>
                        @if (($lane['count'] ?? null) !== null)
                            <span @class([
                                'badge ms-2',
                                'bg-red-lt' => $lane['key'] === 'needs' && (int) $lane['count'] > 0,
                                'bg-secondary-lt' => $lane['key'] !== 'needs' || (int) $lane['count'] === 0,
                            ])>{{ $lane['count'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        @else
            @can(ArkCapability::OperationsAccess->value)
                <li class="nav-item">
                    <a href="{{ route('operations.communications.inbox', ['filter' => 'needs']) }}" class="nav-link text-nowrap">
                        Needs attention
                    </a>
                </li>
            @endcan
        @endif
        @can(ArkCapability::OperationsAccess->value)
            <li class="nav-item">
                <a
                    href="{{ route('operations.communications.calls') }}"
                    @class(['nav-link text-nowrap', 'active' => $section === 'calls'])
                    @if ($section === 'calls') aria-current="page" @endif
                >Calls & VM</a>
            </li>
            <li class="nav-item">
                <a
                    href="{{ route('operations.communications.history') }}"
                    @class(['nav-link text-nowrap', 'active' => $section === 'history'])
                    @if ($section === 'history') aria-current="page" @endif
                >History</a>
            </li>
        @endcan
    </ul>
</nav>
