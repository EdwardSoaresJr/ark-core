<div class="ops-service-lane-band" id="ro-identity-band">
    @include('operations.repair-orders.partials.operational-identity-band-document', [
        'repairOrder' => $repairOrder,
        'identity' => $identity ?? null,
        'identityVariant' => 'staff',
        'estimateVersion' => $estimateVersion ?? null,
        'mileageEditable' => $mileageEditable ?? true,
        'embeddedInServiceLaneBand' => true,
    ])
</div>
