@if (! ($isTerminal ?? false))
    @if (($partsCatalogs ?? []) !== [])
        @include('operations.repair-orders.partials.repair-order-partstech-toolbar-group', [
            'partsCatalogs' => $partsCatalogs,
            'partsCatalogDefault' => $partsCatalogDefault ?? null,
            'concernId' => $concern->id,
        ])
    @endif
@endif
