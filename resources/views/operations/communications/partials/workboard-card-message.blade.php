@php
    /** @var array<string, mixed> $row */
@endphp

<article class="ops-comms-work-card ops-comms-work-card--response">
    <div class="ops-comms-work-card__top">
        <div class="ops-comms-work-card__meta">
            <span class="ops-comms-work-card__chip ops-comms-work-card__chip--channel">{{ $row['channel_label'] ?? 'Message' }}</span>
            <span class="ops-comms-work-card__age">{{ $row['age_label'] ?? '' }}</span>
        </div>
        <span class="ops-comms-work-card__state">{{ $row['state_label'] ?? 'Unread' }}</span>
    </div>

    <p class="ops-comms-work-card__identity">{{ $row['headline'] ?? 'Customer' }} · {{ $row['display_phone'] ?? '' }}</p>
    <p class="ops-comms-work-card__concern">{{ $row['snippet'] ?? '' }}</p>

    @if (filled($row['context_summary'] ?? null))
        <p class="ops-comms-work-card__context">{{ $row['context_summary'] }}</p>
    @endif

    <div class="ops-comms-work-card__actions">
        @if (filled($row['reply_url'] ?? null))
            <a href="{{ $row['reply_url'] }}" class="ops-page-link ops-page-link--primary text-xs">Reply</a>
        @endif
        @if (filled($row['customer_url'] ?? null))
            <a href="{{ $row['customer_url'] }}" class="ops-page-link text-xs">Customer</a>
        @endif
        @if (filled($row['primary_ro_url'] ?? null))
            <a href="{{ $row['primary_ro_url'] }}" class="ops-page-link text-xs">RO</a>
        @endif
        @if (($row['show_mark_read_action'] ?? false) && filled($row['conversation_id'] ?? null))
            <form method="POST" action="{{ route('operations.conversations.read', ['conversation' => $row['conversation_id']]) }}" class="inline">
                @csrf
                <button type="submit" class="ops-page-link text-xs text-slate-500">Mark read</button>
            </form>
        @endif
    </div>
</article>
