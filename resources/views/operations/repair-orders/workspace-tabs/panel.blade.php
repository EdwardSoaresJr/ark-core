@php
    $partial = match ($tab) {
        'comms' => $workspaceMode === 'builder'
            ? 'repair-order-rail-tab-comms-builder'
            : 'repair-order-rail-tab-comms',
        'portal' => 'repair-order-rail-tab-portal',
        'auth' => 'repair-order-rail-tab-auth',
        'parts' => 'repair-order-rail-tab-parts',
        'history' => 'repair-order-rail-tab-history',
        'inspect' => 'repair-order-rail-tab-inspect',
        default => null,
    };
@endphp

@if ($partial)
    @include('operations.repair-orders.partials.'.$partial)
@endif
