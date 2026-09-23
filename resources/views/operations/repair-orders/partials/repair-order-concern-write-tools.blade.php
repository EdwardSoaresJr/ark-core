@if (! ($isTerminal ?? false))
    @if (($laborGuides ?? []) !== [])
        @include('operations.repair-orders.partials.repair-order-labor-guide-intent-button', [
            'laborGuides' => $laborGuides,
            'laborGuideDefault' => $laborGuideDefault ?? null,
            'concernId' => $concern->id,
        ])
    @endif

    @if (($partsCatalogs ?? []) !== [])
        @include('operations.repair-orders.partials.repair-order-partstech-toolbar-group', [
            'partsCatalogs' => $partsCatalogs,
            'partsCatalogDefault' => $partsCatalogDefault ?? null,
            'concernId' => $concern->id,
        ])
    @endif
@endif
