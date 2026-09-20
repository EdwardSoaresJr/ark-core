@php
    /** @var string $title */
    /** @var string $note */
    /** @var array<int, array<string, mixed>> $rows */
    /** @var string $empty */
    /** @var bool $show_timestamp */
    $show_timestamp = $show_timestamp ?? false;
    $count = $count ?? null;
@endphp

<div class="ops-board-shell">
    <x-operations.queue-band-header
        variant="section"
        :label="$title"
        :description="$note"
        :count="$count"
    />

    @if ($rows === [])
        <p class="px-3 py-3 text-sm text-slate-600">{{ $empty }}</p>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach ($rows as $row)
                @include('operations.communications.partials.queue-row', [
                    'row' => $row,
                    'show_timestamp' => $show_timestamp,
                ])
            @endforeach
        </ul>
    @endif
</div>
