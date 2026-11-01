@php
    /** @var array<string, mixed> $shop */
    $statusDot = static fn (string $tone): string => match ($tone) {
        'success' => 'bg-green',
        'warning' => 'bg-yellow',
        'danger' => 'bg-red',
        default => 'bg-secondary',
    };

    $workstationsNeedingDevice = collect($shop['workstations'] ?? [])
        ->filter(fn ($row): bool => $row->deviceCount === 0);
@endphp

<section class="card" aria-labelledby="shop-comms-ready">
    <div class="card-header">
        <h2 class="card-title" id="shop-comms-ready">Ready</h2>
    </div>
    <div class="list-group list-group-flush">
        @foreach ($shop['health'] as $health)
            <div class="list-group-item">
                <div class="d-flex align-items-start gap-2">
                    <span @class(['text-green' => $health->passed, 'text-secondary' => ! $health->passed])>
                        <x-tabler.icon :name="$health->passed ? 'circle-check' : 'circle-dashed'" />
                        <span class="visually-hidden">{{ $health->passed ? 'Passed' : 'Not yet' }}</span>
                    </span>
                    <div>
                        <div class="fw-medium">{{ $health->label }}</div>
                        @if (! $health->passed)
                            <div class="small text-secondary">{{ $health->detail }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
        @foreach ($shop['workstations'] as $station)
            <div class="list-group-item">
                <div class="d-flex align-items-start gap-2">
                    <span @class(['text-green' => $station->isReady, 'text-secondary' => ! $station->isReady])>
                        <x-tabler.icon :name="$station->isReady ? 'circle-check' : 'circle-dashed'" />
                    </span>
                    <div>
                        <div class="fw-medium">{{ $station->name }}</div>
                        @if ($station->isReady)
                            <div class="small text-green">Ready</div>
                        @else
                            <div class="small text-secondary">{{ $station->stationStatusLabel }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

@include('operations.shop.communications.partials.needs-attention-panel', ['shop' => $shop])

<section class="card" aria-labelledby="shop-comms-coverage">
    <div class="card-header">
        <h2 class="card-title" id="shop-comms-coverage">Coverage today</h2>
    </div>
    <div class="list-group list-group-flush">
        @forelse ($shop['coverage'] as $person)
            <a href="{{ route('operations.shop.people.show', $person->userId) }}" class="list-group-item list-group-item-action">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge badge-dot {{ $statusDot($person->statusTone) }} flex-shrink-0" aria-hidden="true"></span>
                    <div class="overflow-hidden">
                        <div class="fw-bold text-truncate">{{ $person->name }}</div>
                        <div class="small text-secondary text-truncate">{{ $person->summary }}</div>
                    </div>
                </div>
            </a>
        @empty
            <div class="list-group-item text-secondary">No staff on the floor yet.</div>
        @endforelse
    </div>
</section>

@include('operations.shop.communications.partials.stations-list', ['shop' => $shop])

@if (($shop['pending_devices'] ?? []) === [] && $workstationsNeedingDevice->isEmpty() && auth()->user()?->is_master_admin)
    <details class="card" @if ($errors->any()) open @endif>
        <summary class="card-header">
            <span class="card-title">Manual device entry (support)</span>
        </summary>
        <div class="card-body p-2 p-md-3">
            @include('operations.shop.communications.partials.add-device-form', ['shop' => $shop, 'workstation' => null])
        </div>
    </details>
@endif
