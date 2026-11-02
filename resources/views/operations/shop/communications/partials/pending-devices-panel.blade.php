@php
    /** @var array<string, mixed> $shop */
    $pending = $shop['pending_devices'] ?? [];
    $stations = $shop['workstations'] ?? [];
    $showIntro = $showIntro ?? true;
    $pendingCount = count($pending);
    $compact = $pendingCount === 1;
    $stationsNeedingDevice = collect($stations)->filter(fn ($row): bool => $row->deviceCount === 0);
    $showWaiting = $stationsNeedingDevice->isNotEmpty() && $pending === [];
@endphp

@if ($pending !== [])
    @foreach ($pending as $device)
        <div @class(['card card-sm' => $showIntro || ! $compact]) data-pending-device="{{ $device->deviceId }}">
            <div @class(['card-body' => $showIntro || ! $compact])>
                @if ($showIntro)
                    <div class="fw-medium">
                        {{ $compact ? 'A new phone is ready to use.' : 'A new phone is ready.' }}
                    </div>
                @endif

                @if (! $compact)
                    <div @class(['fw-bold', 'mt-2' => $showIntro])>{{ $device->modelLabel }}</div>
                    <div class="small text-secondary">{{ $device->foundAgoLabel }}</div>
                @elseif ($showIntro)
                    <div class="fw-bold">{{ $device->modelLabel }}</div>
                @endif

                <div @class(['mt-3', 'pt-3 border-top' => $showIntro || ! $compact])>
                    <div class="fw-medium mb-2">Where should it be used?</div>

                    <div class="d-flex flex-column gap-2">
                        @forelse ($stations as $station)
                            <form
                                method="POST"
                                action="{{ route('operations.shop.devices.assign-station', $device->deviceId) }}"
                                class="d-grid"
                            >
                                @csrf
                                <input type="hidden" name="workstation_id" value="{{ $station->workstationId }}">
                                <button type="submit" class="btn btn-primary">
                                    {{ $station->name }}
                                </button>
                            </form>
                        @empty
                            <div class="small text-secondary">Name a station first - then choose where this phone belongs.</div>
                        @endforelse
                    </div>

                    <form
                        method="POST"
                        action="{{ route('operations.shop.devices.assign-station', $device->deviceId) }}"
                        class="mt-3 pt-3 border-top"
                    >
                        @csrf
                        <label class="visually-hidden" for="new-station-name-{{ $device->deviceId }}">New station name</label>
                        <input
                            id="new-station-name-{{ $device->deviceId }}"
                            type="text"
                            name="new_station_name"
                            value="{{ old('new_station_name') }}"
                            placeholder="New station name"
                            class="form-control mb-2"
                        >
                        <div class="d-grid">
                            <button type="submit" class="btn">Create station</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@elseif ($showWaiting)
    <div class="alert alert-info mb-0" role="status">
        <div>Waiting for a phone to appear… ARK will list it here as soon as it checks in.</div>
        <div class="small text-secondary mt-1">Plug in the phone - ARK keeps checking in until someone chooses where it belongs.</div>
    </div>
@endif
