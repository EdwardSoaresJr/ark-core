@php
    /** @var \App\Ark\Operations\Workboard\WorkboardTriageBoardProjection $workboardTriage */
    $header = $workboardTriage->attentionHeader;
@endphp

@include('operations.workboard.partials.attention-header', ['header' => $header])

@if ($workboardTriage->queueCount === 0)
    <div class="ops-workboard-empty">
        <p class="ops-workboard-empty__title">No active repair orders on the board.</p>
        <p class="ops-workboard-empty__copy">Incoming, active, and outgoing lanes stay empty until work enters the operational queue.</p>
        <a href="{{ route('operations.intake.create') }}" class="ops-workboard-empty__link">+ Check In</a>
    </div>
@else
    @foreach ($workboardTriage->swimlanes as $swimlane)
        @include('operations.workboard.partials.swimlane', [
            'swimlane' => $swimlane,
            'pickupOverflow' => $swimlane->key === 'outgoing' ? $workboardTriage->pickupOverflow : null,
        ])
    @endforeach
@endif
