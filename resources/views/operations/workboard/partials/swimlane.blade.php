@php
    /** @var \App\Ark\Operations\Workboard\WorkboardTriageSwimlaneProjection $swimlane */
    /** @var \App\Ark\Operations\Workboard\WorkboardPickupOverflowProjection|null $pickupOverflow */
@endphp

<section id="ops-swimlane-{{ $swimlane->key }}" class="ops-workboard-swimlane" data-swimlane="{{ $swimlane->key }}">
    <header class="ops-workboard-swimlane__head">
        <h2 class="ops-workboard-swimlane__title">{{ $swimlane->label }}</h2>
    </header>

    <div class="ops-workboard-swimlane__lanes">
        @foreach ($swimlane->lanes as $lane)
            @include('operations.workboard.partials.lane', ['lane' => $lane])
        @endforeach
    </div>

    @if ($pickupOverflow !== null && $pickupOverflow->totalAwaitingPickup > 0)
        <div class="ops-workboard-pickup-overflow">
            <span class="ops-workboard-pickup-overflow__count">
                {{ $pickupOverflow->totalAwaitingPickup }} Awaiting Pickup
                @if ($pickupOverflow->staleCount > 0)
                    <span class="ops-workboard-pickup-overflow__stale">· {{ $pickupOverflow->staleCount }} overdue</span>
                @endif
            </span>
            <a href="{{ $pickupOverflow->viewQueueUrl }}" class="ops-workboard-pickup-overflow__link">View Queue →</a>
        </div>
    @endif
</section>
