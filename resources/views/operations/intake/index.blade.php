<x-operations.app title="Check In">
    @php
        $intakeQueueCount = collect($pressureBands)
            ->sum(fn (array $band): int => collect($band['statuses'])
                ->sum(fn ($status): int => $repairOrdersByStatus->get($status->value, collect())->count()));
    @endphp

    <x-operations.queue-page-header
        title="Check In"
        description="Find the customer, then open the repair order."
        :count="$intakeQueueCount"
        tone="move"
        :show-back="false"
    >
        @can('repair_orders.manage')
            <x-slot:actions>
                <a href="{{ route('operations.intake.create') }}" class="ops-page-link ops-page-link--primary">+ Check In</a>
            </x-slot:actions>
        @endcan
    </x-operations.queue-page-header>

    <section class="ops-intake-queue">
        @foreach ($pressureBands as $pressureBand)
            @php
                $repairOrderCount = collect($pressureBand['statuses'])
                    ->sum(fn ($status): int => $repairOrdersByStatus->get($status->value, collect())->count());
                $bandCount = $repairOrderCount;
            @endphp

            <section
                id="ops-lane-{{ \Illuminate\Support\Str::slug($pressureBand['label']) }}"
                class="ops-pressure-band ops-pressure-band--{{ $pressureBand['tone'] }} ops-queue-band--{{ $pressureBand['tone'] }}"
            >
                <x-operations.queue-band-header
                    variant="lane"
                    :label="$pressureBand['label']"
                    :description="$pressureBand['description']"
                    :count="$bandCount"
                />

                <div class="ops-intake-queue-cards">
                    @if ($bandCount > 0)
                        @foreach ($pressureBand['statuses'] as $queueStatus)
                            @php
                                $statusOrders = $repairOrdersByStatus->get($queueStatus->value, collect());
                            @endphp

                            @foreach ($statusOrders as $repairOrder)
                                @include('operations.intake.partials.qualification-card', [
                                    'repairOrder' => $repairOrder,
                                    'qualification' => $qualifications[$repairOrder->id],
                                ])
                            @endforeach
                        @endforeach
                    @else
                        <p class="ops-intake-queue-empty">No scopes awaiting qualification. Start recognition when someone is at the counter or on a live call.</p>
                    @endif
                </div>
            </section>
        @endforeach
    </section>
</x-operations.app>
