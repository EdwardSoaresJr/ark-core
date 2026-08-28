@php
    /** @var \App\Ark\Operations\Inspections\InspectionPosture|null $inspectionPosture */
    $inspectionPosture = $inspectionPosture ?? null;
    $variant = $variant ?? 'band';
@endphp

@if ($inspectionPosture)
    @if ($variant === 'toolbar')
        @php
            $inspectionTitle = collect([
                $inspectionPosture->headline,
                $inspectionPosture->detail
                    ?? ($inspectionPosture->key === \App\Ark\Operations\Inspections\InspectionPosture::IN_PROGRESS
                        && $inspectionPosture->percentComplete !== null
                            ? $inspectionPosture->percentComplete.'%'
                            : null),
                $inspectionPosture->templateName,
            ])->filter()->implode(' · ');
        @endphp
        <button
            type="button"
            class="ops-visit-signal ops-visit-signal--inspection"
            data-inspection-posture="{{ $inspectionPosture->key }}"
            title="{{ $inspectionTitle }} — open Inspection"
            onclick="window.arkSelectRepairOrderWorkspaceTab?.('inspect')"
        >
            <span class="ops-visit-signal__label">Insp</span>
            <span class="ops-visit-signal__value">{{ $inspectionPosture->headline }}</span>
            @if (filled($inspectionPosture->detail))
                <span class="ops-visit-signal__meta">{{ $inspectionPosture->detail }}</span>
            @elseif ($inspectionPosture->key === \App\Ark\Operations\Inspections\InspectionPosture::IN_PROGRESS && $inspectionPosture->percentComplete !== null)
                <span class="ops-visit-signal__meta">{{ $inspectionPosture->percentComplete }}%</span>
            @endif
            @if (filled($inspectionPosture->templateName))
                <span class="ops-visit-signal__note">{{ $inspectionPosture->templateName }}</span>
            @endif
        </button>
    @else
        <button
            type="button"
            class="ops-inspection-posture mt-1.5 w-full rounded-sm text-left hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-slate-400"
            data-inspection-posture="{{ $inspectionPosture->key }}"
            title="Open Inspection tab"
            onclick="window.arkSelectRepairOrderWorkspaceTab?.('inspect')"
        >
            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Inspection</p>
            <p class="mt-0.5 text-[13px] font-extrabold leading-4 tracking-tight text-slate-950">
                {{ $inspectionPosture->headline }}
            </p>
            @if (filled($inspectionPosture->detail))
                <p class="mt-0.5 text-[11px] font-semibold leading-4 text-slate-700">{{ $inspectionPosture->detail }}</p>
            @elseif ($inspectionPosture->key === \App\Ark\Operations\Inspections\InspectionPosture::IN_PROGRESS && $inspectionPosture->percentComplete !== null)
                <p class="mt-0.5 text-[11px] font-semibold leading-4 text-slate-700">{{ $inspectionPosture->percentComplete }}%</p>
            @endif
            @if (filled($inspectionPosture->templateName))
                <p class="mt-0.5 text-[11px] font-semibold leading-4 text-slate-500">{{ $inspectionPosture->templateName }}</p>
            @endif
        </button>
    @endif
@endif
