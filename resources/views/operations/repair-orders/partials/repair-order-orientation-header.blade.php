@if (is_array($currentSituation ?? null) && isset($workspaceStrip))
    @push('ops-ro-orientation-dock')
    @php
        $strip = $workspaceStrip;
        $identityTitle = collect([
            $strip->roLabel,
            $strip->customerLabel,
            $strip->vehicleLabel,
            $strip->vin,
        ])->filter()->implode(' · ');
    @endphp

    {{--
      Contextual dock — next step, not a status dashboard.
      Identity + situation stay quiet. Footer owns the action.
      Four-column posture lives in the right rail (Persistent Context).
    --}}
    <aside
        class="ops-ro-orientation-header ops-ro-orientation-header--dock ops-ro-orientation-header--footer-first"
        id="ro-orientation-header"
        data-ro-orientation-header
        aria-label="Repair Order next step"
    >
        <div class="ops-ro-orientation-header__identity-row">
            <div
                class="ops-ro-orientation-header__identity text-sm text-slate-950"
                title="{{ $identityTitle }}"
            >
                <span class="ops-ro-orientation-header__segment ops-ro-orientation-header__segment--ro font-extrabold text-slate-950">{{ $strip->roLabel }}</span>
                <span class="ops-ro-orientation-header__sep text-slate-400" aria-hidden="true">·</span>
                <span class="ops-ro-orientation-header__segment ops-ro-orientation-header__segment--customer font-extrabold text-slate-950">{{ $strip->customerLabel }}</span>
                <span class="ops-ro-orientation-header__sep text-slate-400" aria-hidden="true">·</span>
                <span class="ops-ro-orientation-header__segment ops-ro-orientation-header__segment--vehicle font-extrabold text-slate-950">{{ $strip->vehicleLabel }}</span>
            </div>

            <div class="ops-ro-orientation-header__situation-inline">
                {{-- Workflow status label — not orientation "Waiting on Diagnosis" (not every RO is diagnostic). --}}
                <span
                    class="ops-ro-orientation-header__situation"
                    data-ro-dock-status
                >{{ $repairOrder->statusDisplayLabel() }}</span>
                <span
                    class="ops-ro-orientation-header__owner-signal ops-ro-orientation-header__owner-signal--{{ $currentSituation['owner_signal'] }}"
                    aria-hidden="true"
                ></span>
                <span class="ops-ro-orientation-header__owner-label">{{ $currentSituation['owner'] }}</span>
            </div>
        </div>

        @isset($repairOrderFooter)
            @include('operations.repair-orders.partials.repair-order-footer', [
                'repairOrder' => $repairOrder,
                'repairOrderFooter' => $repairOrderFooter,
                'docked' => true,
            ])
        @endisset
    </aside>
    @endpush
@endif
