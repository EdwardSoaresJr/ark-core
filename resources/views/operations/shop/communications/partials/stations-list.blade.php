@php
    /** @var array<string, mixed> $shop */
    $statusText = static fn (string $tone): string => match ($tone) {
        'success' => 'text-success',
        'warning' => 'text-warning',
        'danger' => 'text-danger',
        default => 'text-secondary',
    };
    $activeStep = $shop['active_setup_step'] ?? null;
    $editStationId = (int) old('edit_workstation_id', 0);
@endphp

<section class="card" aria-labelledby="shop-comms-stations" data-shop-comms-stations>
    <div class="card-header">
        <h2 class="card-title" id="shop-comms-stations">Stations</h2>
    </div>

    @error('workstation')
        <div class="alert alert-danger m-2 mb-0" role="alert">{{ $message }}</div>
    @enderror

    <div class="list-group list-group-flush">
        @forelse ($shop['workstations'] as $station)
            @php
                $stationSummary = $station->currentOperatorLabel
                    ? $station->currentOperatorLabel.($station->primaryDeviceName ? ' · '.$station->primaryDeviceName : '')
                    : ($station->primaryDeviceName ?: 'No device attached');
            @endphp
            @if ($station->primaryDeviceId)
                <a href="{{ route('operations.shop.devices.show', $station->primaryDeviceId) }}" class="list-group-item list-group-item-action">
            @else
                <div class="list-group-item">
            @endif
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-fill overflow-hidden">
                        <div class="fw-bold text-truncate">{{ $station->name }}</div>
                        <div class="small text-secondary text-truncate">{{ $stationSummary }}</div>
                    </div>
                    <span class="flex-shrink-0 small fw-bold text-uppercase {{ $statusText($station->stationStatusTone) }}">
                        {{ $station->stationStatusLabel }}
                    </span>
                </div>
            @if ($station->primaryDeviceId)
                </a>
            @else
                </div>
            @endif
        @empty
            <div class="list-group-item text-secondary">No stations yet.</div>
        @endforelse
    </div>

    @if ($activeStep !== 'workstation')
        <details class="border-top">
            <summary class="card-body py-2 fw-medium">Add a station</summary>
            <form method="POST" action="{{ route('operations.shop.workstations.store') }}" class="card-body pt-0">
                @csrf
                <div class="row g-2">
                    <div class="col-12 col-sm-6">
                        <label class="visually-hidden" for="shop-add-station-name">Station name</label>
                        <input
                            id="shop-add-station-name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            placeholder="Front Counter"
                            @class(['form-control', 'is-invalid' => $errors->has('name') && ! $editStationId])
                        >
                        @error('name')
                            @if (! $editStationId)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @endif
                        @enderror
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="visually-hidden" for="shop-add-station-location">Location</label>
                        <input id="shop-add-station-location" type="text" name="location_label" value="{{ old('location_label') }}" placeholder="Location (optional)" class="form-control">
                    </div>
                </div>
                <button type="submit" class="btn btn-sm mt-2">Add station</button>
            </form>
        </details>
    @endif
</section>

@if (($shop['workstation_count'] ?? 0) > 0)
    <details class="card" data-shop-comms-station-names>
        <summary class="card-header">
            <span class="card-title">Manage station names</span>
        </summary>
        <div class="list-group list-group-flush">
            @foreach ($shop['workstations'] as $station)
                <details class="list-group-item" @if ($editStationId === $station->workstationId) open @endif>
                    <summary class="fw-medium">
                        {{ $station->name }}
                    </summary>
                    <form
                        method="POST"
                        action="{{ route('operations.shop.workstations.update', $station->workstationId) }}"
                        class="mt-2"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="edit_workstation_id" value="{{ $station->workstationId }}">
                        <div class="mb-2">
                            <label class="form-label" for="shop-station-{{ $station->workstationId }}-name">Name</label>
                            <input
                                id="shop-station-{{ $station->workstationId }}-name"
                                type="text"
                                name="name"
                                value="{{ old('edit_workstation_id') == $station->workstationId ? old('name', $station->name) : $station->name }}"
                                required
                                class="form-control"
                            >
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="shop-station-{{ $station->workstationId }}-location">Location <span class="text-secondary fw-normal">(optional)</span></label>
                            <input
                                id="shop-station-{{ $station->workstationId }}-location"
                                type="text"
                                name="location_label"
                                value="{{ old('edit_workstation_id') == $station->workstationId ? old('location_label', $station->rawLocationLabel) : ($station->rawLocationLabel ?? '') }}"
                                class="form-control"
                            >
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                    </form>
                </details>
            @endforeach
        </div>
    </details>
@endif
