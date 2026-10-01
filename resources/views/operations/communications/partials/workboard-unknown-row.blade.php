@php
    /** @var array<string, mixed> $row */
@endphp

<div class="flex flex-wrap items-start justify-between gap-3 bg-white px-4 py-3 text-sm">
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">{{ $row['channel_label'] ?? 'Message' }}</span>
            <span class="text-xs text-slate-500">{{ $row['age_label'] ?? '' }}</span>
            <span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-950">Unknown</span>
        </div>
        <p class="mt-1 font-semibold text-slate-950">{{ $row['headline'] ?? 'Unknown' }} · {{ $row['display_phone'] ?? '' }}</p>
        <p class="mt-0.5 text-slate-700 line-clamp-2">{{ $row['snippet'] ?? '' }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if (filled($row['reply_url'] ?? null))
            <a href="{{ $row['reply_url'] }}" class="ops-page-link ops-page-link--primary text-xs">Reply</a>
        @endif
        @if (filled($row['create_lead_url'] ?? null))
            <a href="{{ $row['create_lead_url'] }}" class="ops-page-link text-xs">Check In</a>
        @endif
        @if (filled($row['create_contact_url'] ?? null))
            <a href="{{ $row['create_contact_url'] }}" class="ops-page-link text-xs">Create contact</a>
        @endif
        @if (filled($row['intake_url'] ?? null))
            <a href="{{ $row['intake_url'] }}" class="ops-page-link text-xs">Check In</a>
        @endif
        @if (($row['show_mark_read_action'] ?? false) && filled($row['conversation_id'] ?? null))
            <form method="POST" action="{{ route('operations.conversations.read', ['conversation' => $row['conversation_id']]) }}" class="inline">
                @csrf
                <button type="submit" class="ops-page-link text-xs text-slate-500">Mark read</button>
            </form>
        @endif
    </div>
</div>
