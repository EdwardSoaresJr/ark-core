@php
    /** @var array<string, mixed> $shop */
    $stationsNeedingDevice = collect($shop['workstations'] ?? [])
        ->filter(fn ($row): bool => $row->deviceCount === 0);
    $hasPendingFlow = $stationsNeedingDevice->isNotEmpty() || ($shop['pending_devices'] ?? []) !== [];
@endphp

@if ($hasPendingFlow)
    <section id="connect-device" class="scroll-mt-6 space-y-4 rounded-sm border border-sky-200 bg-white p-4 shadow-sm">
        <header class="space-y-1">
            <h2 class="text-sm font-black text-slate-950">Plug in a phone</h2>
            <ol class="list-decimal space-y-1 pl-4 text-xs leading-5 text-slate-600">
                <li>Plug in a factory-reset phone on the shop network.</li>
                <li>Wait for ARK to notice it.</li>
                <li>Choose where it should be used.</li>
            </ol>
        </header>

        @include('operations.shop.communications.partials.pending-devices-panel', ['shop' => $shop])
    </section>
@endif
