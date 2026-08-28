@php
    /** @var \App\Ark\Operations\Flow\OperationalFlowProjection $flow */
    $displayStages = $flow->displayStages();
    $constraint = $flow->constraint;
@endphp

<div class="ops-today__overview-col" aria-labelledby="ops-today-flow">
    <div class="ops-today__overview-head">
        <h2 id="ops-today-flow" class="ops-today__overview-title">Flow</h2>
        @if ($constraint !== null)
            <p class="ops-today__overview-copy">
                <span class="ops-today-flow__constraint-label">Constraint</span>
                <span class="ops-today-flow__constraint-arrow" aria-hidden="true">→</span>
                <span class="ops-today-flow__constraint-stage">{{ $constraint->label }}</span>
            </p>
        @else
            <p class="ops-today__overview-copy">No open work in motion right now.</p>
        @endif
    </div>

    @if ($displayStages === [])
        <p class="ops-today-flow__empty">No active repair orders in the shop cycle.</p>
    @else
        <ul class="ops-today-flow__stages">
            @foreach ($displayStages as $stage)
                <li>
                    <a
                        href="{{ $stage->inventoryUrl }}"
                        @class([
                            'ops-today-flow__stage',
                            'ops-today-flow__stage--constraint' => $constraint !== null && $stage->stageKey === $constraint->stageKey,
                        ])
                    >
                        <span class="ops-today-flow__stage-label">{{ $stage->label }}</span>
                        <span class="ops-today-flow__stage-count">
                            {{ $stage->count === 1 ? '1 RO' : $stage->count.' ROs' }}
                        </span>
                        @if ($stage->revenueCents > 0 || $stage->oldestAgeMinutes > 0)
                            <span class="ops-today-flow__stage-meta">
                                @if ($stage->revenueCents > 0)
                                    <span class="ops-today-flow__stage-revenue">{{ $stage->revenueLabel }}</span>
                                @endif
                                @if ($stage->revenueCents > 0 && $stage->oldestAgeMinutes > 0)
                                    <span class="ops-today-flow__stage-meta-sep" aria-hidden="true">·</span>
                                @endif
                                @if ($stage->oldestAgeMinutes > 0)
                                    <span class="ops-today-flow__stage-age">Oldest {{ $stage->oldestAgeLabel }}</span>
                                @endif
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($constraint !== null && $constraint->reasons !== [])
        <div class="ops-today-flow__why">
            <p class="ops-today-flow__why-title">Why?</p>
            <ul class="ops-today-flow__why-list">
                @foreach ($constraint->reasons as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
