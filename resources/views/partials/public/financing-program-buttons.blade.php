@php
    $financing = $financing ?? ($trustSignals['financing'] ?? null);
    $trustSignals = $trustSignals ?? ['financing' => $financing];
    $compact = (bool) ($compact ?? false);
@endphp

@if (filled($financing))
    <div @class([
        'public-financing-program-buttons',
        'public-financing-program-buttons--compact' => $compact,
        $class ?? '',
    ])>
        @include('partials.public.wisetack-prequal-button', [
            'trustSignals' => $trustSignals,
        ])
        @include('partials.public.synchrony-apply-button', [
            'trustSignals' => $trustSignals,
        ])
    </div>
@endif
