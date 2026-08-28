@php
    use App\Ark\Runtime\Authorization\ArkCapability;

    /** @var string $section */
    $listFilter = $listFilter ?? request()->string('filter')->toString() ?: null;
    $turnFilter = $turnFilter ?? request()->string('turn')->toString() ?: null;
    $activeKey = match (true) {
        $section === 'history' => 'else',
        $section === 'calls' => 'calls',
        $section === 'internal' => 'internal',
        default => 'inbox',
    };

    $sections = [
        [
            'key' => 'inbox',
            'label' => 'Inbox',
            'href' => route('operations.communications.inbox', ['filter' => 'needs']),
            'permission' => ArkCapability::OperationsAccess->value,
            'count' => is_array($filterCounts ?? null) ? (int) (($filterCounts['needs'] ?? null) ?? ($turnCounts['shop'] ?? 0)) : null,
        ],
        [
            'key' => 'calls',
            'label' => 'Calls & VM',
            'href' => route('operations.communications.calls'),
            'permission' => ArkCapability::OperationsAccess->value,
            'count' => null,
        ],
        // Archive last. Internal team channels have no tab: internal notes
        // live inside each conversation; shop chat is a future capability
        // that must earn its surface (routes remain reachable).
        [
            'key' => 'else',
            'label' => 'History',
            'href' => route('operations.communications.history'),
            'permission' => ArkCapability::OperationsAccess->value,
            'count' => null,
        ],
    ];
@endphp

<nav class="ops-comms-workspace__nav" aria-label="Communications sections">
    <ul class="ops-comms-workspace__nav-list">
        @foreach ($sections as $navSection)
            @can($navSection['permission'])
                <li>
                    <a
                        href="{{ $navSection['href'] }}"
                        @class([
                            'ops-comms-workspace__nav-link',
                            'ops-comms-workspace__nav-link--active' => $activeKey === $navSection['key'],
                        ])
                    >
                        <span>{{ $navSection['label'] }}</span>
                        @if (($navSection['count'] ?? null) !== null && (int) $navSection['count'] > 0)
                            <span class="ops-comms-workspace__nav-count">{{ $navSection['count'] }}</span>
                        @endif
                    </a>
                </li>
            @endcan
        @endforeach
    </ul>
</nav>
