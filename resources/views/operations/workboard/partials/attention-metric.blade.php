@php
    $isActive = $count > 0;
    $classes = 'ops-workboard-attention-metric'.($isActive ? ' ops-workboard-attention-metric--'.$tone : ' ops-workboard-attention-metric--idle');
@endphp

@if ($isActive && $url)
    <a href="{{ $url }}" class="{{ $classes }}">
        <span class="ops-workboard-attention-metric__label">{{ $label }}</span>
        <span class="ops-workboard-attention-metric__count">{{ $count }}</span>
    </a>
@else
    <div class="{{ $classes }}" aria-disabled="true">
        <span class="ops-workboard-attention-metric__label">{{ $label }}</span>
        <span class="ops-workboard-attention-metric__count">{{ $count }}</span>
    </div>
@endif
