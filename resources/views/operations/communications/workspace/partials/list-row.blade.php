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
    $badgeClass = match ($badgeTone) {
        'waiting' => 'bg-yellow-lt',
        'follow' => 'bg-azure-lt',
        'resolved' => 'bg-green-lt',
        default => 'bg-red-lt',
    };
@endphp

<a
    href="{{ $itemUrl }}"
    data-comms-list-row
    data-comms-row-key="{{ $item['key'] ?? '' }}"
    @class(['list-group-item list-group-item-action px-3 py-2', 'active' => $isSelected])
    @if ($isSelected) aria-current="true" @endif
>
    <div class="d-flex gap-2">
        <span class="avatar avatar-sm flex-shrink-0" aria-hidden="true">{{ $initials !== '' ? $initials : '•' }}</span>
        <div class="flex-fill overflow-hidden">
            <div class="d-flex align-items-center gap-1">
                @if (! empty($item['unread']))
                    <span class="badge badge-dot bg-primary flex-shrink-0" role="img" aria-label="Unread" data-comms-unread></span>
                @endif
                <span class="fw-bold text-truncate">{{ $headline }}</span>
                @if ($ageLabel !== '')
                    <span class="ms-auto ps-1 small text-secondary text-nowrap">{{ $ageLabel }}</span>
                @endif
            </div>
            @if ($phone !== '' && $phone !== $headline)
                <div class="small text-secondary text-truncate">{{ $phone }}</div>
            @endif
            @if ($preview !== '')
                <div class="small text-secondary text-truncate">{{ $preview }}</div>
            @endif
            <div class="d-flex flex-wrap align-items-center gap-1 mt-1 small">
                @if ($sourceLabel !== '')
                    <span class="badge bg-secondary-lt">{{ $sourceLabel }}</span>
                @endif
                @if ($badge !== '')
                    <span class="badge {{ $badgeClass }}">{{ $badge }}</span>
                @endif
                @if ($sourceLabel !== '' && $assignedLabel !== '')
                    <span class="text-secondary">{{ $assignedLabel }}</span>
                @endif
                @if ($roHint !== '')
                    <span class="text-secondary text-truncate">{{ $roHint }}</span>
                @endif
                @if ($ownerInitials !== '')
                    <span class="ms-auto fw-bold text-secondary" title="Owner">{{ $ownerInitials }}</span>
                @endif
            </div>
        </div>
    </div>
</a>
