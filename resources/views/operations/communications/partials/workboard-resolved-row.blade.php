@php
    /** @var array<string, mixed> $row */
@endphp

<div class="flex items-center justify-between gap-3 bg-white px-4 py-2.5 text-sm text-slate-700">
    <div class="min-w-0">
        <span class="font-semibold text-slate-900">{{ $row['contact_name'] ?? 'Unknown' }}</span>
        <span class="text-slate-500">· {{ $row['source_label'] ?? '' }} · {{ $row['state_label'] ?? '' }}</span>
        <span class="text-slate-500">· {{ $row['age_label'] ?? '' }}</span>
        <p class="truncate text-slate-600">{{ $row['concern'] ?? '' }}</p>
    </div>
</div>
