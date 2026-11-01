@php
    /** @var array<string, mixed> $shop */
@endphp

@include('operations.shop.communications.partials.voice-setup-steps', ['shop' => $shop])

<div class="alert alert-success mb-0" role="status">
    <div class="fw-bold">Software path complete</div>
    <div>Plug in the phone and run First Contact certification on the device workspace.</div>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        @foreach ($shop['devices'] as $device)
            <a
                href="{{ route('operations.shop.devices.show', $device->deviceId) }}"
                class="list-group-item list-group-item-action"
            >
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-fill overflow-hidden">
                        <div class="fw-bold text-truncate">{{ $device->name }}</div>
                        <div class="small text-secondary text-truncate">
                            {{ $device->workstationName ?? 'Unassigned' }}
                            · {{ $device->statusLabel }}
                        </div>
                    </div>
                    <span class="flex-shrink-0 small fw-medium text-primary">Begin First Contact →</span>
                </div>
            </a>
        @endforeach
    </div>
</div>
