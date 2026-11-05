@php
    /** @var \App\Ark\Operations\Inspections\InspectionPosture|null $inspectionPosture */
    $inspectionPosture = $inspectionPosture ?? null;
@endphp

@if ($inspectionPosture)
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
