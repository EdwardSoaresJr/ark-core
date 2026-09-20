@php
    $serviceLaneLayout = $serviceLaneLayout ?? false;
    $canEdit = $canEdit ?? false;
    $visitLabel = $visitMode?->label() ?? 'Not set';
@endphp

<div class="ops-visit-posture-inline ops-identity-present" data-identity-present="visit-posture">
    <div @class([
        'flex flex-wrap items-baseline gap-x-2 gap-y-0.5',
        'mt-0.5' => ! $serviceLaneLayout,
        'ops-service-lane-ownership-visit-scan' => $serviceLaneLayout,
    ])>
        @if ($canEdit)
            <button
                type="button"
                @class([
                    'ops-identity-title-link',
                    'ops-service-lane-ownership-ro' => $serviceLaneLayout,
                ])
                title="Open to edit visit type"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'visit-posture', invokeEl: $event.currentTarget } }))"
            >
                {{ 'RO #'.$repairOrder->repair_order_id }}
            </button>
        @else
            <span @class([
                'font-extrabold text-slate-950',
                'text-[15px] leading-5 tracking-tight' => ! $serviceLaneLayout,
                'ops-service-lane-ownership-ro' => $serviceLaneLayout,
            ])>{{ 'RO #'.$repairOrder->repair_order_id }}</span>
        @endif
        @if ($serviceLaneLayout)
            <span class="ops-service-lane-sep">·</span>
        @endif
        @if ($canEdit)
            <button
                type="button"
                @class([
                    'font-bold text-slate-600 hover:text-slate-950',
                    'text-xs' => ! $serviceLaneLayout,
                    'ops-service-lane-ownership-visit-mode' => $serviceLaneLayout,
                ])
                title="Open to edit visit type"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'visit-posture', invokeEl: $event.currentTarget } }))"
            >{{ $visitLabel }}</button>
        @else
            <span @class([
                'font-bold text-slate-600',
                'text-xs' => ! $serviceLaneLayout,
                'ops-service-lane-ownership-visit-mode' => $serviceLaneLayout,
            ])>{{ $visitLabel }}</span>
        @endif
    </div>
</div>
