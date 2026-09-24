@php
    $itemUrl = $item['select_url'] ?? $item['url'] ?? '#';
    $headline = $item['headline'] ?? $item['name'] ?? 'Unknown';
    $phone = trim((string) ($item['phone'] ?? $item['subtitle'] ?? ''));
    $preview = trim((string) ($item['preview'] ?? $item['snippet'] ?? ''));
    $ageLabel = trim((string) ($item['age_label'] ?? ''));
    $badge = trim((string) ($item['badge'] ?? ''));
    $badgeTone = (string) ($item['badge_tone'] ?? 'needs');
    $initials = (string) ($item['initials'] ?? '');
    $ownerInitials = (string) ($item['owner_initials'] ?? '');
    $sourceLabel = trim((string) ($item['source_label'] ?? ''));
    $assignedLabel = trim((string) ($item['assigned_label'] ?? ''));
    $roHint = trim(implode(' · ', array_filter([
        $item['ro_label'] ?? null,
        $item['vehicle_label'] ?? null,
    ])));
@endphp

<a
    href="{{ $itemUrl }}"
    data-comms-row-key="{{ $item['key'] ?? '' }}"
    @class([
        'ops-comms-inbox__card',
        'ops-comms-workspace__list-row',
        'ops-comms-inbox__card--active' => $isSelected,
        'ops-comms-workspace__list-row--active' => $isSelected,
    ])
>
    <span class="ops-comms-inbox__avatar" aria-hidden="true">{{ $initials !== '' ? $initials : '•' }}</span>
    <span class="ops-comms-inbox__card-body">
        <span class="ops-comms-inbox__card-top">
            <span class="ops-comms-inbox__name">{{ $headline }}</span>
            @if ($ageLabel !== '')
                <span class="ops-comms-inbox__age">{{ $ageLabel }}</span>
            @endif
        </span>
        @if ($phone !== '' && $phone !== $headline)
            <span class="ops-comms-inbox__phone">{{ $phone }}</span>
        @endif
        @if ($preview !== '')
            <span class="ops-comms-inbox__preview">{{ $preview }}</span>
        @endif
        <span class="ops-comms-inbox__card-meta">
            @if ($sourceLabel !== '')
                <span class="ops-comms-inbox__badge">{{ $sourceLabel }}</span>
            @endif
            @if ($badge !== '')
                <span @class(['ops-comms-inbox__badge', 'ops-comms-inbox__badge--'.$badgeTone])>{{ $badge }}</span>
            @endif
            @if ($sourceLabel !== '' && $assignedLabel !== '')
                <span class="ops-comms-inbox__ro">{{ $assignedLabel }}</span>
            @endif
            @if ($roHint !== '')
                <span class="ops-comms-inbox__ro">{{ $roHint }}</span>
            @endif
            @if ($ownerInitials !== '')
                <span class="ops-comms-inbox__owner-mark">{{ $ownerInitials }}</span>
            @endif
        </span>
    </span>
</a>
