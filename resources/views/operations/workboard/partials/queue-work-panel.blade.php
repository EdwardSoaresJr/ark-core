@php
    /** @var \App\Ark\Operations\Workboard\WorkboardQueueWorkspaceProjection $workboardWorkspace */
@endphp

<div class="ops-ops-workspace__panel">
    @if ($workboardWorkspace->boardIsEmpty)
        <div class="ops-workboard-empty">
            <p class="ops-workboard-empty__title">No cars on the board yet.</p>
            @if (\App\Ark\Operations\Settings\ShopSettings::current()->appointmentsEnabled())
                <p class="ops-workboard-empty__copy">Check in a walk-in, or open Schedule for tomorrow’s appointments.</p>
            @else
                <p class="ops-workboard-empty__copy">Check in a walk-in — that’s how cars land on this board.</p>
            @endif
            <a href="{{ route('operations.intake.create') }}" class="ops-workboard-empty__link">+ Check In</a>
        </div>
    @elseif ($workboardWorkspace->selectedQueueKey === null)
        <div class="ops-ops-workspace__choose">
            <p class="ops-ops-workspace__choose-title">Choose a queue</p>
            <p class="ops-ops-workspace__choose-copy">Pick a queue on the left to see repair orders waiting on you.</p>
        </div>
    @else
        <div class="ops-ops-workspace__panel-header">
            <h2 class="ops-ops-workspace__panel-title">{{ $workboardWorkspace->selectedQueueLabel }}</h2>
            @if ($workboardWorkspace->selectedQueueCount > 0)
                <x-operations.pressure-count :count="$workboardWorkspace->selectedQueueCount" inline />
            @endif
        </div>

        @if ($workboardWorkspace->selectedQueueCount === 0)
            <p class="ops-ops-workspace__empty">Nothing in this queue right now.</p>
        @else
            <div class="ops-ops-workspace__cards">
                @foreach ($workboardWorkspace->visibleCards as $card)
                    @include('operations.workboard.partials.card', ['card' => $card])
                @endforeach
            </div>

            @if ($workboardWorkspace->hiddenCount > 0 && $workboardWorkspace->viewAllUrl !== null)
                <p class="ops-ops-workspace__overflow">
                    <a href="{{ $workboardWorkspace->viewAllUrl }}" class="ops-ops-workspace__overflow-link">
                        +{{ $workboardWorkspace->hiddenCount }} more in inventory →
                    </a>
                </p>
            @endif
        @endif

        @if ($workboardWorkspace->pickupOverflow !== null)
            @include('operations.workboard.partials.pickup-overflow', ['overflow' => $workboardWorkspace->pickupOverflow])
        @endif
    @endif
</div>
