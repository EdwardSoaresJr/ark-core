@props([
    'title',
    'note' => null,
    'rows' => [],
    'empty' => 'Nothing in this projection right now.',
    'showScheduleActions' => true,
    'compactActions' => false,
    'variant' => 'stacked',
    'tone' => 'approval',
    'compactHeader' => true,
])

@if ($variant === 'lane')
    @php
        $laneSlug = Str::slug($title);
        $laneColor = match ($tone) {
            'blocked' => 'yellow',
            'move' => 'secondary',
            default => 'orange',
        };
        $laneCount = count($rows);
    @endphp

    <section
        id="ops-decision-lane-{{ $laneSlug }}"
        class="card"
        aria-labelledby="ops-decision-lane-{{ $laneSlug }}-title"
        data-decision-lane="{{ $tone }}"
    >
        <div class="card-status-top bg-{{ $laneColor }}"></div>
        <div class="card-header">
            <div class="min-w-0">
                <h3 id="ops-decision-lane-{{ $laneSlug }}-title" class="card-title">
                    @if ($compactHeader ?? true)
                        <a href="{{ route('operations.work.queue', 'decisions') }}" class="text-reset" title="Open full {{ $title }} queue">{{ $title }}</a>
                    @else
                        {{ $title }}
                    @endif
                    @if ($laneCount > 0)
                        <span class="badge bg-{{ $laneColor }}-lt ms-1" data-decision-lane-count>{{ $laneCount }}</span>
                    @endif
                </h3>
                @if (filled($note))
                    <div class="card-subtitle">{{ $note }}</div>
                @endif
            </div>
            @unless ($compactHeader ?? true)
                <div class="card-actions">
                    <a href="{{ route('operations.index') }}" class="btn btn-sm">Back to Work</a>
                </div>
            @endunless
        </div>

        @if ($rows === [])
            <div class="card-body text-secondary">{{ $empty }}</div>
        @elseif ($compactActions)
            <ul class="divide-y divide-slate-100">
                @include('operations.attention.partials.customer-decision-pressure-rows', [
                    'rows' => $rows,
                    'showScheduleActions' => $showScheduleActions,
                    'compactActions' => true,
                ])
            </ul>
        @else
            <div class="list-group list-group-flush">
                @include('operations.attention.partials.customer-decision-pressure-rows', [
                    'rows' => $rows,
                    'showScheduleActions' => $showScheduleActions,
                    'compactActions' => false,
                ])
            </div>
        @endif
    </section>
@else
    <section class="ops-decision-pressure-section">
        <div class="ops-decision-pressure-section-head">
            <h4 class="ops-decision-pressure-section-title">{{ $title }}</h4>
            @if ($note)
                <p class="ops-decision-pressure-section-note">{{ $note }}</p>
            @endif
        </div>

        @if ($rows === [])
            <p class="px-3 py-2 text-sm text-slate-600">{{ $empty }}</p>
        @else
            <ul class="divide-y divide-slate-100">
                @include('operations.attention.partials.customer-decision-pressure-rows', [
                    'rows' => $rows,
                    'showScheduleActions' => $showScheduleActions,
                    'compactActions' => $compactActions,
                ])
            </ul>
        @endif
    </section>
@endif
