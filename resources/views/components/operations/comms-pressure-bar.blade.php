@props([
    'pressure',
    'ui' => 'legacy',
])

@php
    $count = (int) ($pressure['count'] ?? 0);
    $summary = is_array($pressure['summary'] ?? null) ? $pressure['summary'] : [];
    $url = (string) ($pressure['attention_url'] ?? route('operations.index'));
    $live = (bool) ($pressure['has_live_calls'] ?? false);
@endphp

@if ($count > 0 && $ui === 'tabler')
    <div class="container-fluid pt-3">
        <div
            @class(['alert mb-0', 'alert-success' => $live, 'alert-danger' => ! $live])
            role="status"
            aria-live="polite"
            data-ops-comms-pressure-bar="{{ $live ? 'live' : 'attention' }}"
        >
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="flex-fill">
                    <strong>{{ $count }}</strong>
                    {{ $count === 1 ? 'customer needs you' : 'customers need you' }}
                    @if (($summary['breakdown_label'] ?? '') !== '')
                        <span class="text-secondary">· {{ $summary['breakdown_label'] }}</span>
                    @endif
                </div>
                <a href="{{ $url }}" class="btn btn-sm">Open Communications</a>
            </div>
        </div>
    </div>
@elseif ($count > 0)
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
