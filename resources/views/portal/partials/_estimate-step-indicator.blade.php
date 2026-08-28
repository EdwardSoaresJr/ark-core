@props([
    'current' => 'review',
    'depositEnabled' => false,
    'payingRemaining' => false,
])

@php
    $steps = $depositEnabled
        ? [
            'review' => 'Review',
            'authorize' => 'Approve',
            'pay_deposit' => $payingRemaining ? 'Pay remaining' : 'Pay deposit',
            'done' => 'Done',
        ]
        : [
            'review' => 'Review',
            'authorize' => 'Approve',
            'done' => 'Done',
        ];
    $order = array_keys($steps);
    $currentIndex = array_search($current, $order, true);
@endphp

<nav aria-label="Estimate progress">
    <ol
        @class([
            'portal-estimate-stepper',
            'portal-estimate-stepper--'.count($steps) => count($steps) !== 3,
        ])
        style="--portal-estimate-steps: {{ count($steps) }}"
    >
        @foreach ($steps as $key => $label)
            @php
                $index = array_search($key, $order, true);
                $isComplete = $currentIndex !== false && $index !== false && $index < $currentIndex;
                $isCurrent = $key === $current;
            @endphp
            <li @class([
                'portal-estimate-stepper__step',
                'portal-estimate-stepper__step--current' => $isCurrent,
                'portal-estimate-stepper__step--complete' => $isComplete,
            ])>
                <span class="portal-estimate-stepper__marker" aria-hidden="true">
                    @if ($isComplete)
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" /></svg>
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span class="portal-estimate-stepper__label">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</nav>
