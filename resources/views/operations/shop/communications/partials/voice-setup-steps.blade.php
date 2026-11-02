@php
    /** @var array<string, mixed> $shop */
    $activeStep = $shop['active_setup_step'];
@endphp

<ol class="steps steps-green my-2" data-shop-comms-setup-steps>
    @foreach ($shop['setup_steps'] as $step)
        <li
            @class(['step-item', 'active' => ! $step['passed'] && $step['key'] === $activeStep])
            @if (! $step['passed'] && $step['key'] === $activeStep) aria-current="step" @endif
        >
            @if ($step['passed'])
                <span class="visually-hidden">Done:</span>
            @endif
            {{ $step['label'] }}
        </li>
    @endforeach
</ol>
