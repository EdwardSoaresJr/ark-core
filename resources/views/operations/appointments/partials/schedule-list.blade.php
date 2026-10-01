@props([
    'groups' => [],
    'empty' => 'No appointments yet. Schedule the next drop-off.',
])

@php
    $totalCount = (int) ($groups['total_count'] ?? 0);
    $bucketLabels = [
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'upcoming' => 'Upcoming',
    ];
@endphp

@if ($totalCount === 0)
    <p class="px-3 py-2 text-sm text-slate-600">{{ $empty }}</p>
@else
    @foreach ($bucketLabels as $bucket => $label)
        @if (($groups[$bucket] ?? []) !== [])
            <div class="border-t border-slate-100">
                <p class="ops-schedule-bucket-label">{{ $label }}</p>
                <ul class="divide-y divide-slate-100">
                    @foreach ($groups[$bucket] as $row)
                        @include('operations.appointments.partials.schedule-row', [
                            'row' => $row,
                            'showDayLabel' => $bucket === 'upcoming',
                        ])
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
@endif
