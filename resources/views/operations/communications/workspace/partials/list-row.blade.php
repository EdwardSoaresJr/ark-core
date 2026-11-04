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
    $unread = ! empty($item['unread']);
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
    @class(['list-group-item list-group-item-action ark-comms-row px-3 py-2', 'active' => $isSelected, 'ark-comms-row--unread' => $unread])
    @if ($isSelected) aria-current="true" @endif
>
    <div class="d-flex gap-3">
        <x-tabler.avatar :name="$headline" :initials="$initials" class="flex-shrink-0" />
        <div class="flex-fill overflow-hidden">
            <div class="d-flex align-items-center gap-1">
                <span @class(['text-truncate', 'fw-bold' => $unread, 'fw-medium' => ! $unread])>{{ $headline }}</span>
                @if ($ageLabel !== '')
                    <span @class(['ms-auto ps-1 small text-nowrap', 'text-primary fw-medium' => $unread, 'text-secondary' => ! $unread])>{{ $ageLabel }}</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-2">
                <div @class(['small text-truncate flex-fill', 'text-body' => $unread, 'text-secondary' => ! $unread])>{{ $preview !== '' ? $preview : $phone }}</div>
                @if ($unread)
                    <span class="badge badge-dot bg-primary flex-shrink-0" role="img" aria-label="Unread" data-comms-unread></span>
                @endif
            </div>
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
                @php
                    $meta = implode(' · ', array_filter([$roHint, $phone !== $headline && $preview !== '' ? $phone : '']));
                @endphp
                @if ($meta !== '')
                    <span class="text-secondary text-truncate">{{ $meta }}</span>
                @endif
                @if ($ownerInitials !== '')
                    <span class="avatar avatar-xs ms-auto" title="Owner">{{ $ownerInitials }}</span>
                @endif
            </div>
        </div>
    </div>
</a>
