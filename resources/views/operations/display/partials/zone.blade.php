@php
    /** @var \App\Ark\Operations\Today\AdvisorHomeAttentionZone $zone */
@endphp

<section
    @class([
        'ops-shop-display-zone',
        'ops-shop-display-zone--' . $zone->key->value,
    ])
    aria-labelledby="ops-shop-display-zone-{{ $zone->key->value }}"
>
    <header class="ops-shop-display-zone__header">
        <h2 class="ops-shop-display-zone__title" id="ops-shop-display-zone-{{ $zone->key->value }}">
            {{ $zone->label }}
            <span class="ops-shop-display-zone__count">({{ $zone->count }})</span>
        </h2>
    </header>

    @if ($zone->rows === [])
        <p class="ops-shop-display-zone__empty">No repair orders</p>
    @else
        <div class="ops-shop-display-zone__cards">
            @foreach ($zone->rows as $row)
                @include('operations.display.partials.card', ['row' => $row])
            @endforeach
        </div>
    @endif
</section>
