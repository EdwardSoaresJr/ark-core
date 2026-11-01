@php
    $steps = [
        'customer' => 'Customer',
        'vehicle' => 'Vehicle',
        'open' => 'Open RO',
    ];
@endphp

<nav aria-label="Check In progress">
    <ol class="steps steps-counter steps-green my-0" data-intake-steps>
        @foreach ($steps as $key => $label)
            <li @class(['step-item', 'active' => $intakeStep === $key]) @if ($intakeStep === $key) aria-current="step" @endif>{{ $label }}</li>
        @endforeach
    </ol>
</nav>
