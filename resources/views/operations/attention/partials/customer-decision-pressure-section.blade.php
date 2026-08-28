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
        $bandClass = match ($tone) {
            'approval' => 'ops-home-band--decision-needed',
            'blocked' => 'ops-home-band--decision-stalled',
            'move' => 'ops-home-band--decision-ready',
            default => 'ops-home-band--decision-needed',
        };
    @endphp

    <section
        id="ops-decision-lane-{{ $laneSlug }}"
        @class(['ops-home-band', $bandClass, 'ops-home-band--decision-lane'])
        aria-labelledby="ops-decision-lane-{{ $laneSlug }}-title"
    >
        @include('operations.work.partials.work-queue-band-header', [
            'id' => 'ops-decision-lane-'.$laneSlug.'-title',
            'title' => $title,
            'count' => count($rows),
            'queue' => 'decisions',
            'compact' => $compactHeader ?? true,
            'subtitle' => $note,
        ])

        <div class="ops-home-band-body p-0">
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
        </div>
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
