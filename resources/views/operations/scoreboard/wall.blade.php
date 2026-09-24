<x-layouts.operations-scoreboard-wall
    title="Shop scoreboard"
    :refresh-seconds="60"
    :fragment-url="route('operations.owner.scoreboard', ['display' => 'wall', 'fragment' => 1, 'period' => $periodKey])"
>
    <div id="shop-scoreboard-board" class="h-full overflow-auto">
        @include('operations.scoreboard.partials.board')
    </div>
</x-layouts.operations-scoreboard-wall>
