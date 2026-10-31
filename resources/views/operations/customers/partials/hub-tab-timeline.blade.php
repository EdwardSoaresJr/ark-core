@php
    $hasTimelineEntries = $vehicleTimelineEntries->contains(fn ($entries) => $entries->isNotEmpty());
@endphp

@if ($hasTimelineEntries)
    @foreach ($customer->vehicles as $vehicle)
        @php
            $vehicleTimeline = $vehicleTimelineEntries->get($vehicle->id, collect());
        @endphp

        @if ($vehicleTimeline->isNotEmpty())
            <section id="vehicle-timeline-{{ $vehicle->id }}" class="border-top">
                <div class="card-body d-flex flex-wrap align-items-baseline justify-content-between gap-2 py-2">
                    <a
                        href="{{ route('operations.customers.show', ['customer' => $customer, 'vehicle' => $vehicle->id]) }}"
                        class="fw-bold text-reset"
                    >{{ $vehicle->operational_identity }}</a>
                    <span class="subheader">{{ $vehicleTimeline->count() }} {{ Str::plural('event', $vehicleTimeline->count()) }}</span>
                </div>

                <div class="list-group list-group-flush">
                    @foreach ($vehicleTimeline as $entry)
                        <div class="list-group-item">
                            <div class="d-flex align-items-start justify-content-between gap-3 small">
                                <div>
                                    <div class="fw-bold">{{ $entry['title'] }}</div>
                                    <div class="text-secondary">{{ $entry['detail'] }}</div>
                                    @if ($entry['actor'] ?? null)
                                        <div class="subheader mt-1">{{ $entry['actor'] }}</div>
                                    @endif
                                </div>
                                <span class="flex-shrink-0 text-secondary">
                                    {{ $entry['occurred_at']?->timezone(config('app.display_timezone'))->format('M j, g:i A') }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
@else
    <div class="card-body text-secondary">No operational timeline events yet.</div>
@endif
