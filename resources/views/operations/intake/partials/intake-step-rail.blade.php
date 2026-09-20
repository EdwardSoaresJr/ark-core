@php
    $steps = [
        'customer' => 'Customer',
        'vehicle' => 'Vehicle',
        'open' => 'Open RO',
    ];
@endphp

<nav class="ops-intake-step-rail" aria-label="Check In progress">
    @foreach ($steps as $key => $label)
        @php
            $isComplete = match ($intakeStep) {
                'customer' => false,
                'vehicle' => $key === 'customer',
                'open' => in_array($key, ['customer', 'vehicle'], true),
            };
            $isCurrent = $intakeStep === $key;
        @endphp
        <span @class([
            'ops-intake-step',
            'ops-intake-step--complete' => $isComplete,
            'ops-intake-step--current' => $isCurrent,
        ])>
            <span class="ops-intake-step-marker" aria-hidden="true">{{ $isComplete ? '✓' : ($loop->iteration) }}</span>
            <span class="ops-intake-step-label">{{ $label }}</span>
        </span>
    @endforeach
</nav>
