@php
    /** @var string $id */
    /** @var string $label */
    /** @var string|null $description */
    /** @var int $count */
    /** @var string $tone */
    /** @var list<array<string, mixed>> $rows */
    /** @var string $cardPartial */
    /** @var string $emptyLabel */
@endphp

<section id="{{ $id }}" class="ops-pressure-band ops-pressure-band--{{ $tone }} ops-queue-band--{{ $tone }}">
    <x-operations.queue-band-header
        variant="lane"
        :id="$id.'-header'"
        :label="$label"
        :description="$description"
        :count="$count"
    />

    <div class="ops-radar-cards">
        @forelse ($rows as $row)
            @include($cardPartial, ['row' => $row])
        @empty
            <p class="px-1 py-2 text-[11px] font-semibold text-slate-400">{{ $emptyLabel }}</p>
        @endforelse
    </div>
</section>
