@php
    use App\Ark\Runtime\Authorization\ArkCapability;

    $listFilter = $listFilter ?? request()->string('filter')->toString() ?: 'needs';
    $ownerFilter = $ownerFilter ?? 'everyone';
    $filterCounts = is_array($filterCounts ?? null) ? $filterCounts : [];
    $channelOptions = is_array($channelOptions ?? null) ? $channelOptions : [];
    $activeChannel = collect($channelOptions)->firstWhere('active', true) ?? ($channelOptions[0] ?? null);
    $channelValue = $activeChannel !== null && $activeChannel['value'] !== 'all' ? $activeChannel['value'] : null;
    $selection = array_filter([
        'conversation' => request()->integer('conversation') ?: null,
        'lead' => request()->integer('lead') ?: null,
        'call' => request()->integer('call') ?: null,
        'platform_conversation' => request()->string('platform_conversation')->toString() ?: null,
        'owner' => $ownerFilter !== 'everyone' ? $ownerFilter : null,
        'channel' => $channelValue,
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

<div class="d-flex align-items-center gap-2 flex-shrink-0 mb-2" data-comms-section-bar>
    <nav class="flex-fill overflow-auto" aria-label="Communications sections" data-comms-section-nav>
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
                                    'bg-primary-lt' => $lane['key'] === 'needs' && (int) $lane['count'] > 0,
                                    'bg-secondary-lt' => $lane['key'] !== 'needs' || (int) $lane['count'] === 0,
                                ]) data-comms-lane-count="{{ $lane['key'] }}">{{ $lane['count'] }}</span>
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

    @if ($section === 'inbox' && $activeChannel !== null)
        <div class="dropdown flex-shrink-0" data-comms-channel-filter>
            <button
                type="button"
                @class(['btn btn-sm dropdown-toggle', 'btn-outline-primary' => $channelValue !== null])
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="Channel: {{ $activeChannel['label'] }}"
            >
                <x-tabler.icon :name="$activeChannel['icon']" class="me-1" />
                <span class="d-none d-sm-inline">{{ $activeChannel['label'] }}</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <span class="dropdown-header">Channel</span>
                @foreach ($channelOptions as $channelOption)
                    <a
                        href="{{ route('operations.communications.inbox', array_merge($selection, ['filter' => $listFilter, 'channel' => $channelOption['value'] === 'all' ? null : $channelOption['value']])) }}"
                        @class(['dropdown-item', 'active' => $channelOption['active']])
                        @if ($channelOption['active']) aria-current="page" @endif
                        data-comms-channel-option="{{ $channelOption['value'] }}"
                    >
                        <x-tabler.icon :name="$channelOption['icon']" class="me-2 text-secondary" />
                        {{ $channelOption['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
