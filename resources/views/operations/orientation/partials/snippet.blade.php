@props([
    'orientation' => null,
    'density' => 'compact',
    'ui' => 'legacy',
])

@php
    $orientation = is_array($orientation ?? null) ? $orientation : null;
    $density = (string) ($orientation['density'] ?? $density ?? 'compact');
@endphp

@if ($orientation !== null && filled($orientation['situation'] ?? null) && ($ui ?? 'legacy') === 'tabler')
    @php
        $ownerSignal = (string) ($orientation['owner_signal'] ?? 'system');
        $ownerSignalColor = match ($ownerSignal) {
            'customer' => 'green',
            'warranty' => 'yellow',
            'technician' => 'azure',
            'parts' => 'orange',
            default => 'secondary',
        };
    @endphp
    <div class="border rounded p-2 small" data-orientation-snippet>
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="fw-medium">{{ $orientation['situation'] }}</div>
            @if (filled($orientation['owner'] ?? null))
                <span class="d-inline-flex align-items-center gap-1 text-secondary">
                    <span class="status-dot status-{{ $ownerSignalColor }}" data-orientation-owner-signal="{{ $ownerSignal }}" aria-hidden="true"></span>
                    {{ $orientation['owner'] }}
                </span>
            @endif
        </div>

        @if (filled($orientation['progress_stopped_because'] ?? null))
            <p class="mt-1 mb-0">{{ $orientation['progress_stopped_because'] }}</p>
        @endif

        @if ($density === 'full' || $density === 'standard')
            @if (! empty($orientation['suggested_follow_up_lines']))
                <div class="mt-1 text-secondary">
                    @foreach ($orientation['suggested_follow_up_lines'] as $line)
                        <p class="mb-0">{{ $line }}</p>
                    @endforeach
                </div>
            @elseif (filled($orientation['next_action'] ?? null))
                <p class="mt-1 mb-0 text-secondary">{{ $orientation['next_action'] }}</p>
            @endif

            @if (! empty($orientation['confidence_items']))
                <ul class="mt-1 mb-0 ps-3 text-secondary" style="list-style: disc">
                    @foreach ($orientation['confidence_items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        @elseif (filled($orientation['next_action'] ?? null))
            <p class="mt-1 mb-0 text-secondary">{{ $orientation['next_action'] }}</p>
        @endif
    </div>
@elseif ($orientation !== null && filled($orientation['situation'] ?? null))
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
