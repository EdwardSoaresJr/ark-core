@props([
    'pressure',
])

@php
    $count = (int) ($pressure['count'] ?? 0);
    $summary = is_array($pressure['summary'] ?? null) ? $pressure['summary'] : [];
    $url = (string) ($pressure['attention_url'] ?? route('operations.index'));
    $live = (bool) ($pressure['has_live_calls'] ?? false);
@endphp

@if ($count > 0)
    <div
        class="ops-comms-pressure-bar {{ $live ? 'ops-comms-pressure-bar--live' : 'ops-comms-pressure-bar--attention' }}"
        role="status"
        aria-live="polite"
    >
        <div class="ops-comms-pressure-bar__inner">
            <p class="ops-comms-pressure-bar__copy">
                <span class="ops-comms-pressure-bar__count">{{ $count }}</span>
                {{ $count === 1 ? 'customer needs you' : 'customers need you' }}
                @if (($summary['breakdown_label'] ?? '') !== '')
                    <span class="ops-comms-pressure-bar__meta">· {{ $summary['breakdown_label'] }}</span>
                @endif
            </p>
            <a href="{{ $url }}" class="ops-comms-pressure-bar__action">
                Open Communications
            </a>
        </div>
    </div>
@endif
