@php
    /** @var array<string, mixed> $row */
@endphp

<article class="ops-comms-work-card ops-comms-work-card--opportunity">
    <div class="ops-comms-work-card__top">
        <div class="ops-comms-work-card__meta">
            <span class="ops-comms-work-card__chip ops-comms-work-card__chip--source">{{ $row['source_label'] ?? 'Lead' }}</span>
            <span class="ops-comms-work-card__age">{{ $row['age_label'] ?? '' }}</span>
        </div>
        <span class="ops-comms-work-card__state">{{ $row['state_label'] ?? '' }}</span>
    </div>

    <p class="ops-comms-work-card__identity">{{ $row['contact_name'] ?? 'Unknown' }} · {{ $row['display_phone'] ?? '—' }}</p>
    <p class="ops-comms-work-card__concern">{{ $row['concern'] ?? '' }}</p>

    <div class="ops-comms-work-card__actions">
        @if (filled($row['reply_url'] ?? null))
            <a href="{{ $row['reply_url'] }}" class="ops-page-link ops-page-link--primary text-xs">Reply</a>
        @endif
        <a href="{{ $row['intake_url'] ?? '#' }}" class="ops-page-link text-xs">Check In</a>
        @if (filled($row['lead_id'] ?? null))
            <form method="POST" action="{{ route('operations.leads.state', ['lead' => $row['lead_id']]) }}" class="inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="state" value="contacted">
                <button type="submit" class="ops-page-link text-xs">Contacted</button>
            </form>
            <form method="POST" action="{{ route('operations.leads.state', ['lead' => $row['lead_id']]) }}" class="inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="state" value="waiting_customer">
                <button type="submit" class="ops-page-link text-xs">Waiting</button>
            </form>
            <form method="POST" action="{{ route('operations.leads.state', ['lead' => $row['lead_id']]) }}" class="inline" onsubmit="return confirm('Mark this lead as lost?');">
                @csrf
                @method('PATCH')
                <input type="hidden" name="state" value="lost">
                <input type="hidden" name="lost_reason" value="Closed without RO">
                <button type="submit" class="ops-page-link text-xs text-rose-700">Lost</button>
            </form>
            <form method="POST" action="{{ route('operations.leads.state', ['lead' => $row['lead_id']]) }}" class="inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="state" value="spam">
                <button type="submit" class="ops-page-link text-xs text-slate-500">Spam</button>
            </form>
        @endif
    </div>
</article>
