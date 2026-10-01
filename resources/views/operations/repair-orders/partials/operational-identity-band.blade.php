@php
    $identityVariant = $identityVariant ?? 'staff';
@endphp

@if ($identityVariant === 'staff' && isset($repairOrder))
    @include('operations.repair-orders.partials.service-lane-identity-band', [
        'repairOrder' => $repairOrder,
        'identity' => $identity ?? null,
        'serviceLane' => $serviceLane ?? null,
        'totals' => $totals ?? null,
        'estimateVersion' => $estimateVersion ?? null,
        'mileageEditable' => $mileageEditable ?? true,
    ])
    @include('operations.repair-orders.partials.repair-order-lost-reason-record', [
        'repairOrder' => $repairOrder,
    ])
@else
    @include('operations.repair-orders.partials.operational-identity-band-document', [
        'repairOrder' => $repairOrder,
        'identity' => $identity ?? null,
        'identityVariant' => $identityVariant,
        'estimateVersion' => $estimateVersion ?? null,
        'mileageEditable' => $mileageEditable ?? true,
    ])
@endif
