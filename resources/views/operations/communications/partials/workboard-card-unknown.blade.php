@php
    /** @var array<string, mixed> $row */
@endphp

<article class="ops-comms-work-card ops-comms-work-card--unknown">
    <div class="ops-comms-work-card__top">
        <div class="ops-comms-work-card__meta">
            <span class="ops-comms-work-card__chip ops-comms-work-card__chip--unknown">{{ $row['channel_label'] ?? 'Unknown' }}</span>
            <span class="ops-comms-work-card__age">{{ $row['age_label'] ?? '' }}</span>
        </div>
        <span class="ops-comms-work-card__state">Unmatched</span>
    </div>

    <p class="ops-comms-work-card__identity">{{ $row['headline'] ?? 'Unknown' }} · {{ $row['display_phone'] ?? '' }}</p>
    <p class="ops-comms-work-card__concern">{{ $row['snippet'] ?? '' }}</p>

    <div class="ops-comms-work-card__actions">
        @if (filled($row['reply_url'] ?? null))
            <a href="{{ $row['reply_url'] }}" class="ops-page-link ops-page-link--primary text-xs">Reply</a>
        @endif
        @if (filled($row['create_lead_url'] ?? null))
            <a href="{{ $row['create_lead_url'] }}" class="ops-page-link text-xs">Check In</a>
        @endif
        @if (($row['show_mark_read_action'] ?? false) && filled($row['conversation_id'] ?? null))
            <form method="POST" action="{{ route('operations.conversations.read', ['conversation' => $row['conversation_id']]) }}" class="inline">
                @csrf
                <button type="submit" class="ops-page-link text-xs text-slate-500">Mark read</button>
            </form>
        @endif
    </div>
</article>
