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

<nav class="ops-comms-workspace__nav" aria-label="Communications sections">
    <ul class="ops-comms-workspace__nav-list">
        @if ($section === 'inbox')
            @foreach ($lanes as $lane)
                <li>
                    <a
                        href="{{ route('operations.communications.inbox', array_merge($selection, ['filter' => $lane['key']])) }}"
                        title="{{ $lane['title'] }}"
                        @class([
                            'ops-comms-workspace__nav-link',
                            'ops-comms-workspace__nav-link--active' => $listFilter === $lane['key'],
                        ])
                    >
                        <span>{{ $lane['label'] }}</span>
                        @if (($lane['count'] ?? null) !== null)
                            <span class="ops-comms-workspace__nav-count">{{ $lane['count'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        @else
            @can(ArkCapability::OperationsAccess->value)
                <li>
                    <a href="{{ route('operations.communications.inbox', ['filter' => 'needs']) }}" class="ops-comms-workspace__nav-link">
                        <span>Needs attention</span>
                    </a>
                </li>
            @endcan
        @endif
        @can(ArkCapability::OperationsAccess->value)
            <li>
                <a href="{{ route('operations.communications.calls') }}" @class(['ops-comms-workspace__nav-link', 'ops-comms-workspace__nav-link--active' => $section === 'calls'])>Calls & VM</a>
            </li>
            <li>
                <a href="{{ route('operations.communications.history') }}" @class(['ops-comms-workspace__nav-link', 'ops-comms-workspace__nav-link--active' => $section === 'history'])>History</a>
            </li>
        @endcan
    </ul>
</nav>
