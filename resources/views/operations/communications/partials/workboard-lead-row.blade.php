@php
    /** @var array<string, mixed> $row */
@endphp

<div class="flex flex-wrap items-start justify-between gap-3 bg-white px-4 py-3 text-sm">
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-900">{{ $row['source_label'] ?? 'Lead' }}</span>
            <span class="text-xs text-slate-500">{{ $row['age_label'] ?? '' }}</span>
            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800">{{ $row['state_label'] ?? '' }}</span>
        </div>
        <p class="mt-1 font-semibold text-slate-950">{{ $row['contact_name'] ?? 'Unknown' }} · {{ $row['display_phone'] ?? '—' }}</p>
        <p class="mt-0.5 font-medium text-slate-800">{{ $row['concern'] ?? '' }}</p>
        @if (filled($row['snippet'] ?? null) && ($row['snippet'] ?? '') !== ($row['concern'] ?? ''))
            <p class="mt-1 text-slate-600 line-clamp-2">{{ $row['snippet'] }}</p>
        @endif
    </div>
    <div class="flex flex-wrap gap-2">
        @if (filled($row['reply_url'] ?? null))
            <a href="{{ $row['reply_url'] }}" class="ops-page-link ops-page-link--primary text-xs">Reply</a>
        @endif
        <a href="{{ $row['intake_url'] ?? '#' }}" class="ops-page-link text-xs">Check In</a>
        @if (filled($row['create_contact_url'] ?? null))
            <a href="{{ $row['create_contact_url'] }}" class="ops-page-link text-xs">Create contact</a>
        @endif
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
</div>
