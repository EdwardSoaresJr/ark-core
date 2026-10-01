@props([
    'projection',
    'empty' => 'No appointments scheduled for this week.',
])

@if (($projection['total_count'] ?? 0) === 0)
    <p class="px-3 py-6 text-sm text-slate-600">{{ $empty }}</p>
@else
    @foreach ($projection['days'] as $day)
        <section class="ops-schedule-day border-b border-slate-200 last:border-b-0">
            <p class="ops-schedule-day-label">{{ $day['day_label'] }}</p>
            <ul class="divide-y divide-slate-100">
                @foreach ($day['rows'] as $row)
                    @include('operations.appointments.partials.schedule-row', ['row' => $row])
                @endforeach
            </ul>
        </section>
    @endforeach
@endif
