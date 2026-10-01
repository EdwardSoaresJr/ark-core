@php
    /** @var \App\Ark\Operations\Workboard\WorkboardPickupOverflowProjection $overflow */
@endphp

<div class="ops-workboard-pickup-overflow">
    <span class="ops-workboard-pickup-overflow__count">
        {{ $overflow->totalAwaitingPickup }} Awaiting Pickup
        @if ($overflow->staleCount > 0)
            <span class="ops-workboard-pickup-overflow__stale">· {{ $overflow->staleCount }} overdue</span>
        @endif
    </span>
    <a href="{{ $overflow->viewQueueUrl }}" class="ops-workboard-pickup-overflow__link">View Queue →</a>
</div>
