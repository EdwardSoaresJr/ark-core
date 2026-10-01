@php
    $hasTimelineEntries = $vehicleTimelineEntries->contains(fn ($entries) => $entries->isNotEmpty());
@endphp

@if ($hasTimelineEntries)
    <div class="divide-y divide-slate-100">
        @foreach ($customer->vehicles as $vehicle)
            @php
                $vehicleTimeline = $vehicleTimelineEntries->get($vehicle->id, collect());
            @endphp

            @if ($vehicleTimeline->isNotEmpty())
                <section id="vehicle-timeline-{{ $vehicle->id }}" class="px-3 py-2.5">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 pb-2">
                        <a
                            href="{{ route('operations.customers.show', ['customer' => $customer, 'vehicle' => $vehicle->id]) }}"
                            class="text-sm font-black text-slate-950 hover:text-slate-700"
                        >{{ $vehicle->operational_identity }}</a>
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $vehicleTimeline->count() }} {{ Str::plural('event', $vehicleTimeline->count()) }}</p>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @foreach ($vehicleTimeline as $entry)
                            <div class="py-2 text-xs">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-950">{{ $entry['title'] }}</p>
                                        <p class="mt-0.5 text-slate-500">{{ $entry['detail'] }}</p>
                                        @if ($entry['actor'] ?? null)
                                            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.06em] text-slate-400">{{ $entry['actor'] }}</p>
                                        @endif
                                    </div>
                                    <p class="shrink-0 font-semibold tabular-nums text-slate-400">
                                        {{ $entry['occurred_at']?->timezone(config('app.display_timezone'))->format('M j, g:i A') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
@else
    <p class="px-3 py-8 text-sm text-slate-600">No operational timeline events yet.</p>
@endif
