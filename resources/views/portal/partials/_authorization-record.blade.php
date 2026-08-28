@props([
    'record',
    'title' => 'Approval on file',
    'intro' => null,
])

@php
    $approvedBy = $record['approved_by'] ?? 'Customer';
    $approvedAt = $record['approved_at_label'] ?? null;
    $sourceLabel = $record['source_label'] ?? null;
    $approvedAmount = $record['approved_amount'] ?? null;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900']) }}>
    <p class="font-semibold">{{ $title }}</p>

    @if (filled($intro))
        <p class="mt-1">{{ $intro }}</p>
    @endif

    <dl class="mt-2 space-y-1 text-emerald-950">
        <div class="flex flex-wrap gap-x-1">
            <dt class="font-semibold">Approved by</dt>
            <dd>{{ $approvedBy }}</dd>
        </div>
        @if (filled($approvedAt))
            <div class="flex flex-wrap gap-x-1">
                <dt class="font-semibold">Date</dt>
                <dd>{{ $approvedAt }}</dd>
            </div>
        @endif
        @if (filled($sourceLabel))
            <div class="flex flex-wrap gap-x-1">
                <dt class="font-semibold">Method</dt>
                <dd>{{ $sourceLabel }}</dd>
            </div>
        @endif
        @if (filled($approvedAmount))
            <div class="flex flex-wrap gap-x-1">
                <dt class="font-semibold">Approved amount</dt>
                <dd class="font-semibold tabular-nums">{{ $approvedAmount }}</dd>
            </div>
        @endif
    </dl>
</div>
