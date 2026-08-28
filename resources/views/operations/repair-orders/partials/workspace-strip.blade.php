@php
    $strip = $workspaceStrip;
    $identityTitle = collect([
        $strip->roLabel,
        $strip->customerLabel,
        $strip->vehicleLabel,
        $strip->vin,
    ])->filter()->implode(' · ');
@endphp

{{-- Sticky identity only — next actions live in the contextual footer --}}
<div class="ops-workspace-strip" data-workspace-strip aria-hidden="true">
    <div class="ops-workspace-strip__identity text-sm text-slate-950" title="{{ $identityTitle }}">
        <span class="ops-workspace-strip__segment ops-workspace-strip__segment--ro font-extrabold text-slate-950">{{ $strip->roLabel }}</span>
        <span class="ops-workspace-strip__sep text-slate-400" aria-hidden="true">·</span>
        <span class="ops-workspace-strip__segment ops-workspace-strip__segment--customer font-extrabold text-slate-950">{{ $strip->customerLabel }}</span>
        <span class="ops-workspace-strip__sep text-slate-400" aria-hidden="true">·</span>
        <span class="ops-workspace-strip__segment ops-workspace-strip__segment--vehicle font-extrabold text-slate-950">{{ $strip->vehicleLabel }}</span>
        @if (filled($strip->vin))
            <span class="ops-workspace-strip__sep" aria-hidden="true">·</span>
            <span class="ops-workspace-strip__segment ops-workspace-strip__segment--vin">
                <x-operations.vin-display :vin="$strip->vin" class="ops-vin-display--strip" />
            </span>
        @endif
    </div>
</div>
