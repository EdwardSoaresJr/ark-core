@props([
    'orientation' => null,
    'density' => 'compact',
])

@php
    $orientation = is_array($orientation ?? null) ? $orientation : null;
    $density = (string) ($orientation['density'] ?? $density ?? 'compact');
@endphp

@if ($orientation !== null && filled($orientation['situation'] ?? null))
    <div @class([
        'ops-orientation-snippet',
        'ops-orientation-snippet--' . $density => true,
    ])>
        <div class="ops-orientation-snippet__head">
            <p class="ops-orientation-snippet__situation">{{ $orientation['situation'] }}</p>
            @if (filled($orientation['owner'] ?? null))
                <div class="ops-orientation-snippet__owner">
                    <span
                        class="ops-orientation-snippet__owner-signal ops-orientation-snippet__owner-signal--{{ $orientation['owner_signal'] ?? 'advisor' }}"
                        aria-hidden="true"
                    ></span>
                    <span class="ops-orientation-snippet__owner-label">{{ $orientation['owner'] }}</span>
                </div>
            @endif
        </div>

        @if (filled($orientation['progress_stopped_because'] ?? null))
            <p class="ops-orientation-snippet__story">{{ $orientation['progress_stopped_because'] }}</p>
        @endif

        @if ($density === 'full' || $density === 'standard')
            @if (! empty($orientation['suggested_follow_up_lines']))
                <div class="ops-orientation-snippet__follow-up">
                    @foreach ($orientation['suggested_follow_up_lines'] as $line)
                        <p>{{ $line }}</p>
                    @endforeach
                </div>
            @elseif (filled($orientation['next_action'] ?? null))
                <p class="ops-orientation-snippet__next">{{ $orientation['next_action'] }}</p>
            @endif

            @if (! empty($orientation['confidence_items']))
                <ul class="ops-orientation-snippet__confidence">
                    @foreach ($orientation['confidence_items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        @elseif (filled($orientation['next_action'] ?? null))
            <p class="ops-orientation-snippet__next">{{ $orientation['next_action'] }}</p>
        @endif
    </div>
@endif
