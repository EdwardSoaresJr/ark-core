@php
    /** @var array<string, mixed> $shop */
    $pending = $shop['pending_devices'] ?? [];
    $attention = $shop['attention'] ?? [];
    $pendingCount = (int) ($shop['pending_device_count'] ?? count($pending));
    $hasAttention = $pendingCount > 0 || $attention !== [];
    $pendingSummary = match (true) {
        $pendingCount === 1 => 'A new phone is ready to use.',
        $pendingCount > 1 => $pendingCount.' new phones are ready to use.',
        default => null,
    };
@endphp

@if ($hasAttention)
    <section class="card" aria-labelledby="shop-comms-attention">
        <div class="card-status-top bg-yellow"></div>
        <div class="card-header">
            <h2 class="card-title" id="shop-comms-attention">Needs attention</h2>
        </div>

        @if ($pendingCount > 0)
            @if ($pendingCount === 1)
                <div class="card-body p-2 p-md-3">
                    @include('operations.shop.communications.partials.pending-devices-panel', [
                        'shop' => $shop,
                        'showIntro' => true,
                    ])
                </div>
            @else
                <details class="border-bottom" @if ($errors->any()) open @endif>
                    <summary class="card-body py-2 fw-bold">
                        {{ $pendingSummary }}
                    </summary>
                    <div class="card-body p-2 p-md-3 pt-0 d-flex flex-column gap-3">
                        @include('operations.shop.communications.partials.pending-devices-panel', [
                            'shop' => $shop,
                            'showIntro' => false,
                        ])
                    </div>
                </details>
            @endif
        @endif

        @if ($attention !== [])
            <div class="list-group list-group-flush">
                @foreach ($attention as $item)
                    @if ($item->deviceId !== null)
                        <a href="{{ route('operations.shop.devices.show', $item->deviceId) }}" class="list-group-item list-group-item-action">
                            <span class="badge badge-dot bg-yellow me-2" aria-hidden="true"></span>
                            <span class="fw-medium">{{ $item->message }}</span>
                        </a>
                    @else
                        <div class="list-group-item">
                            <span class="badge badge-dot bg-yellow me-2" aria-hidden="true"></span>
                            <span class="fw-medium">{{ $item->message }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </section>
@endif
