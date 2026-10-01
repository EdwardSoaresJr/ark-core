@php
    /** @var \App\Ark\Operations\Display\ShopDisplayBoardProjection $display */
@endphp

<x-layouts.operations-display :refreshSeconds="$refreshSeconds">
    <div class="ops-shop-display" aria-label="Shop display">
        <div id="ops-shop-display-board">
            @include('operations.display.partials.board', ['display' => $display])
        </div>
    </div>
</x-layouts.operations-display>
