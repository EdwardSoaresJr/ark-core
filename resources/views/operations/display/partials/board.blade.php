@php
    /** @var \App\Ark\Operations\Display\ShopDisplayBoardProjection $display */
@endphp

<header class="ops-shop-display__header" aria-label="Shop summary">
    <div class="ops-shop-display__metric">
        <span class="ops-shop-display__metric-label">Active Cars</span>
        <span class="ops-shop-display__metric-value">{{ $display->activeCarCount }}</span>
    </div>
    <div class="ops-shop-display__metric">
        <span class="ops-shop-display__metric-label">Needs Action</span>
        <span class="ops-shop-display__metric-value">{{ $display->needsActionCount }}</span>
    </div>
    <div class="ops-shop-display__metric">
        <span class="ops-shop-display__metric-label">Active Work</span>
        <span class="ops-shop-display__metric-value">{{ $display->activeWorkCount }}</span>
    </div>
    <div class="ops-shop-display__metric">
        <span class="ops-shop-display__metric-label">Ready Pickup</span>
        <span class="ops-shop-display__metric-value">{{ $display->readyPickupCount }}</span>
    </div>
    <div class="ops-shop-display__refreshed">
        Last updated {{ $display->refreshedAtLabel }}
    </div>
</header>

<div class="ops-shop-display__lanes">
    @foreach ($display->attentionZones as $zone)
        @include('operations.display.partials.zone', ['zone' => $zone])
    @endforeach
</div>
