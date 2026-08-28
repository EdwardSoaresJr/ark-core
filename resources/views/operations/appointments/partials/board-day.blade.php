<div class="ops-board-shell ops-cal-agenda">
    @if (($w['total_count'] ?? 0) === 0)
        <div class="border-b border-slate-100 px-3 py-2">
            <p class="text-sm font-semibold text-slate-800">No appointments yet.</p>
            <p class="mt-0.5 text-xs text-slate-600">Click a time slot below, or use Schedule to book the next drop-off.</p>
        </div>
    @endif
    <div class="ops-cal-day" style="--ops-cal-grid-height: {{ $gridHeight }}px;">
        <div class="ops-cal-day__times" aria-hidden="true">
            @foreach ($w['slots'] as $slot)
                <div class="ops-cal-day__tick" style="height: {{ (int) round($w['slot_minutes'] * $pxPerMinute) }}px;">
                    {{ $slot['label'] }}
                </div>
            @endforeach
        </div>

        <div class="ops-cal-day__lanes">
            @foreach ($w['lane_rows'] as $lane)
                <div
                    class="ops-cal-lane"
                    data-ops-cal-lane
                    style="min-height: {{ $gridHeight }}px; height: {{ $gridHeight }}px;"
                >
                    <div class="ops-cal-lane__head">{{ $lane['label'] }}</div>
                    <div class="ops-cal-lane__body" data-ops-cal-lane-body>
                        @foreach ($w['slots'] as $slot)
                            <a
                                href="{{ route('operations.schedule', [
                                    'starts_at' => $slot['starts_at'],
                                    'ends_at' => $slot['ends_at'],
                                ]) }}"
                                class="ops-cal-slot"
                                style="height: {{ (int) round($w['slot_minutes'] * $pxPerMinute) }}px;"
                                title="Schedule {{ $slot['label'] }}"
                                data-ops-cal-slot
                            ><span class="sr-only">Schedule {{ $slot['label'] }}</span></a>
                        @endforeach

                        @foreach ($lane['cards'] as $card)
                            @include('operations.appointments.partials.calendar-card', [
                                'card' => $card,
                                'pxPerMinute' => $pxPerMinute,
                            ])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
