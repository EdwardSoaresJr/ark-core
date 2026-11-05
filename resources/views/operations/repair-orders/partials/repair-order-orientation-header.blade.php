@if (is_array($currentSituation ?? null) && isset($workspaceStrip))
    @php
        $strip = $workspaceStrip;
        $identityTitle = collect([
            $strip->roLabel,
            $strip->customerLabel,
            $strip->vehicleLabel,
            $strip->vin,
        ])->filter()->implode(' · ');
        $ownerSignal = (string) ($currentSituation['owner_signal'] ?? 'system');
        $ownerSignalColor = match ($ownerSignal) {
            'customer' => 'green',
            'warranty' => 'yellow',
            'technician' => 'azure',
            'parts' => 'orange',
            default => 'secondary',
        };
    @endphp

    {{--
      Contextual dock - next step, not a status dashboard. It sticks to the bottom of the viewport as the last
      child of the page body. Worksheet continuity replaces it by its id; the rail and inspect popovers measure it
      through data-ro-orientation-header.
    --}}
    <aside
        class="card sticky-bottom mt-3 shadow-lg"
        id="ro-orientation-header"
        data-ro-orientation-header
        aria-label="Repair Order next step"
    >
        <div class="card-status-top bg-primary"></div>
        <div class="card-body px-2 px-md-3 py-1 py-md-2">
            {{-- One line on every width. Phones keep RO, customer and vehicle; status and owner text show from sm. --}}
            <div class="d-flex flex-nowrap align-items-baseline justify-content-between gap-2 small" data-ro-dock-summary>
                <div class="d-flex gap-1 gap-sm-2 min-w-0 text-truncate fw-bold" title="{{ $identityTitle }}">
                    <span class="flex-shrink-0">{{ $strip->roLabel }}</span>
                    <span class="text-secondary" aria-hidden="true">·</span>
                    <span class="text-truncate">{{ $strip->customerLabel }}</span>
                    <span class="text-secondary" aria-hidden="true">·</span>
                    <span class="text-truncate">{{ $strip->vehicleLabel }}</span>
                </div>

                <div class="d-flex align-items-center gap-2 min-w-0 ms-auto" title="{{ $repairOrder->statusDisplayLabel() }} · {{ $currentSituation['owner'] }}">
                    {{-- Workflow status label - not orientation "Waiting on Diagnosis" (not every RO is diagnostic). --}}
                    <span class="fw-bold text-truncate d-none d-sm-inline" data-ro-dock-status>{{ $repairOrder->statusDisplayLabel() }}</span>
                    <span class="status-dot status-{{ $ownerSignalColor }} flex-shrink-0" data-ro-owner-signal="{{ $ownerSignal }}" aria-hidden="true"></span>
                    <span class="text-secondary flex-shrink-0 d-none d-sm-inline">{{ $currentSituation['owner'] }}</span>
                </div>
            </div>

            @isset($repairOrderFooter)
                @include('operations.repair-orders.partials.repair-order-footer', [
                    'repairOrder' => $repairOrder,
                    'repairOrderFooter' => $repairOrderFooter,
                    'docked' => true,
                ])
            @endisset
        </div>
    </aside>
@endif
