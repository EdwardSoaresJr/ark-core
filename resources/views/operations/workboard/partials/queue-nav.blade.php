@php
    /** @var \App\Ark\Operations\Workboard\WorkboardQueueWorkspaceProjection $workboardWorkspace */
@endphp

<nav class="ops-ops-workspace__nav" aria-label="Operations queues">
    <p class="ops-ops-workspace__nav-title">Workboard</p>

    <p class="ops-ops-workspace__nav-section">Needs Attention</p>
    <ul class="ops-ops-workspace__nav-list">
        <li>
            @if ($workboardWorkspace->needsAttentionRollupUrl !== null)
                <a
                    href="{{ $workboardWorkspace->needsAttentionRollupUrl }}"
                    @class([
                        'ops-ops-workspace__nav-link',
                        'ops-ops-workspace__nav-link--active' => $workboardWorkspace->needsAttentionRollupIsActive,
                    ])
                >
                    <span class="ops-ops-workspace__nav-label">Needs Attention</span>
                    <span class="ops-ops-workspace__nav-count">{{ $workboardWorkspace->needsAttentionRollup }}</span>
                </a>
            @else
                <span class="ops-ops-workspace__nav-link ops-ops-workspace__nav-link--static">
                    <span class="ops-ops-workspace__nav-label">Needs Attention</span>
                    <span class="ops-ops-workspace__nav-count">{{ $workboardWorkspace->needsAttentionRollup }}</span>
                </span>
            @endif
        </li>
    </ul>

    @foreach ($workboardWorkspace->navGroups as $group)
        @if ($group->label !== null)
            <p class="ops-ops-workspace__nav-section">{{ $group->label }}</p>
        @endif

        <ul class="ops-ops-workspace__nav-list">
            @foreach ($group->items as $item)
                <li>
                    <a
                        href="{{ $item->url }}"
                        @class([
                            'ops-ops-workspace__nav-link',
                            'ops-ops-workspace__nav-link--active' => $item->isActive,
                        ])
                    >
                        <span class="ops-ops-workspace__nav-label">{{ $item->label }}</span>
                        <span @class([
                            'ops-ops-workspace__nav-count',
                            'ops-ops-workspace__nav-count--warn' => $item->countSeverity === 'warn',
                            'ops-ops-workspace__nav-count--alert' => $item->countSeverity === 'alert',
                        ])>{{ $item->count }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endforeach
</nav>
