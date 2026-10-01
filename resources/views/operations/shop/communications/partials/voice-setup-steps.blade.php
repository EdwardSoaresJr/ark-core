@php
    /** @var array<string, mixed> $shop */
    $activeStep = $shop['active_setup_step'];
@endphp

<ol class="flex flex-wrap items-center gap-x-1 gap-y-2 text-xs font-semibold">
    @foreach ($shop['setup_steps'] as $index => $step)
        @if ($index > 0)
            <li class="text-slate-300" aria-hidden="true">→</li>
        @endif
        <li @class([
            'inline-flex items-center gap-1.5 rounded-sm px-2 py-1',
            'bg-emerald-50 text-emerald-800' => $step['passed'],
            'bg-slate-950 text-white' => ! $step['passed'] && $step['key'] === $activeStep,
            'text-slate-500' => ! $step['passed'] && $step['key'] !== $activeStep,
        ])>
            <span aria-hidden="true">{{ $step['passed'] ? '✓' : ($step['key'] === $activeStep ? '●' : '○') }}</span>
            {{ $step['label'] }}
        </li>
    @endforeach
</ol>
