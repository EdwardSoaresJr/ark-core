@php
    /** @var array<string, mixed> $item */
    /** @var bool $isSelected */
    $itemUrl = $item['select_url'] ?? $item['url'] ?? '#';
    $headline = $item['headline'] ?? $item['name'] ?? 'Unknown contact';
    $phone = trim((string) ($item['phone'] ?? $item['subtitle'] ?? ''));
    $email = trim((string) ($item['email'] ?? ''));
    $reason = trim((string) ($item['reason'] ?? ''));
    $shopHint = trim((string) ($item['shop_hint'] ?? ''));
    $preview = trim((string) ($item['preview'] ?? $item['snippet'] ?? ''));
    $channelLabel = trim((string) ($item['channel_label'] ?? ''));
    $originLabel = trim((string) ($item['origin_label'] ?? ''));
    $ageLabel = trim((string) ($item['age_label'] ?? ''));
    $pressureScore = $item['pressure_score'] ?? null;
    $known = (bool) ($item['known_customer'] ?? filled($item['customer_id'] ?? null));
    $linkStatus = trim((string) ($item['link_status'] ?? ($known ? '' : 'No customer')));
    // Preview already folds snippet + reason together — prefer it so rows
    // read like an inbox (latest message first), not a status board.
    $reasonLine = $preview !== '' ? $preview : ($reason !== '' ? $reason : '');
    $originChip = $originLabel !== ''
        ? $originLabel
        : (str_contains(strtolower($channelLabel), 'lead') ? $channelLabel : '');
    $channelChip = ($originChip === '' && $channelLabel !== '') ? $channelLabel : '';
@endphp

<a
    href="{{ $itemUrl }}"
    @class([
        'ops-comms-workspace__list-row',
        'ops-comms-workspace__list-row--active' => $isSelected,
        'ops-comms-workspace__list-row--pressure' => $pressureScore !== null && (int) $pressureScore >= 70,
        'ops-comms-workspace__list-row--unknown' => ! $known,
    ])
    @if ($channelLabel !== '') title="{{ $channelLabel }}" @endif
>
    <div class="ops-comms-workspace__list-row-line">
        <span class="ops-comms-workspace__list-headline">{{ $headline }}</span>
        @if ($ageLabel !== '')
            <span class="ops-comms-workspace__list-meta">{{ $ageLabel }}</span>
        @endif
    </div>
    @if ($phone !== '' && $phone !== $headline)
        <p class="ops-comms-workspace__list-phone">
            {{ $phone }}@if ($email !== '') <span class="ops-comms-workspace__list-email-inline">· {{ $email }}</span> @endif
        </p>
    @elseif ($email !== '')
        <p class="ops-comms-workspace__list-phone">{{ $email }}</p>
    @endif
    @if ($originChip !== '' || $channelChip !== '')
        <p class="ops-comms-workspace__list-chips">
            @if ($originChip !== '')
                <span class="ops-comms-workspace__chip ops-comms-workspace__chip--origin">{{ $originChip }}</span>
            @elseif ($channelChip !== '')
                <span class="ops-comms-workspace__chip ops-comms-workspace__chip--channel">{{ $channelChip }}</span>
            @endif
        </p>
    @endif
    @if (! $known && $linkStatus !== '')
        <p class="ops-comms-workspace__list-unknown">{{ $linkStatus }}</p>
    @endif
    @if ($reasonLine !== '')
        <p class="ops-comms-workspace__list-reason-line">
            <span class="ops-comms-workspace__list-reason-text">{{ $reasonLine }}</span>
        </p>
    @endif
    @if ($shopHint !== '' && $shopHint !== $reasonLine && $shopHint !== $phone && $shopHint !== $email)
        <p class="ops-comms-workspace__list-shop-hint">{{ $shopHint }}</p>
    @endif
</a>
