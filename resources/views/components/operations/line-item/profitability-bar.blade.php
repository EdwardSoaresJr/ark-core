@props([
    'percent',
    'fillPercent',
    'tone' => 'healthy',
    'label' => 'Healthy',
])

<div {{ $attributes->class(['ops-profitability-bar', 'ops-profitability-bar--'.$tone]) }}>
    <div class="ops-profitability-bar__track" aria-hidden="true">
        <span class="ops-profitability-bar__fill" style="width: {{ $fillPercent }}%;"></span>
    </div>
    <span class="ops-profitability-bar__label">{{ $label }} · {{ $percent }}%</span>
</div>
