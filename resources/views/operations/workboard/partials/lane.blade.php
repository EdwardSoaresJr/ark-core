@php
    /** @var \App\Ark\Operations\Workboard\WorkboardTriageLaneProjection $lane */
@endphp

<section id="ops-lane-{{ $lane->key }}" class="ops-workboard-lane ops-pressure-band ops-pressure-band--{{ $lane->tone }} ops-queue-band--{{ $lane->tone }}">
    <header class="ops-workboard-lane__head ops-pressure-band-header">
        @if ($lane->inventoryUrl && $lane->totalCount > 0)
            <a href="{{ $lane->inventoryUrl }}" class="ops-workboard-lane__inventory-link">
                <h3 class="ops-workboard-lane__title">{{ $lane->label }}</h3>
                <span class="ops-workboard-lane__count">({{ $lane->totalCount }})</span>
            </a>
        @else
            <h3 class="ops-workboard-lane__title">{{ $lane->label }}</h3>
            <span class="ops-workboard-lane__count">({{ $lane->totalCount }})</span>
        @endif
    </header>

    <div class="ops-workboard-lane__cards">
        @forelse ($lane->visibleCards as $card)
            @include('operations.workboard.partials.card', ['card' => $card])
        @empty
            <p class="ops-workboard-lane__empty">No work in this lane.</p>
        @endforelse

        @if ($lane->hiddenCount > 0 && $lane->viewAllUrl)
            <a href="{{ $lane->viewAllUrl }}" class="ops-workboard-lane__more">+{{ $lane->hiddenCount }} more</a>
        @endif
    </div>
</section>
