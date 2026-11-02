@php
    /** @var array<string, mixed> $shop */
    $stationsNeedingDevice = collect($shop['workstations'] ?? [])
        ->filter(fn ($row): bool => $row->deviceCount === 0);
    $hasPendingFlow = $stationsNeedingDevice->isNotEmpty() || ($shop['pending_devices'] ?? []) !== [];
@endphp

@if ($hasPendingFlow)
    <section id="connect-device" class="card" aria-labelledby="connect-device-title">
        <div class="card-status-top bg-azure"></div>
        <div class="card-header">
            <div>
                <h2 class="card-title" id="connect-device-title">Plug in a phone</h2>
                <ol class="card-subtitle mb-0 ps-3" style="list-style: decimal">
                    <li>Plug in a factory-reset phone on the shop network.</li>
                    <li>Wait for ARK to notice it.</li>
                    <li>Choose where it should be used.</li>
                </ol>
            </div>
        </div>
        <div class="card-body p-2 p-md-3 d-flex flex-column gap-3">
            @include('operations.shop.communications.partials.pending-devices-panel', ['shop' => $shop])
        </div>
    </section>
@endif
