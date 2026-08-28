@php
    $identity = $identity ?? App\Ark\Operations\RepairOrders\OperationalIdentityPresenter::fromSnapshot(
        $snapshot,
        customerFacing: ($variant ?? 'show') === 'pdf',
    );
    $identityVariant = ($variant ?? 'show') === 'pdf' ? 'document-pdf' : 'document';
@endphp

@if (($variant ?? 'show') === 'pdf')
    @include('operations.documents.partials._pdf-identity-band', ['identity' => $identity])
@else
    @include('operations.repair-orders.partials.operational-identity-band', [
        'identity' => $identity,
        'identityVariant' => $identityVariant,
        'repairOrder' => $repairOrder,
    ])
@endif
