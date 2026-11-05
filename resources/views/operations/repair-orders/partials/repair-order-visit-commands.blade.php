{{-- Worksheet saves replace #review-toolbar from the server, so every control whose state can change lives inside it. --}}
<div id="review-toolbar" class="ark-ro-visit-commands" data-ro-command-bar>
    @include('operations.repair-orders.partials.repair-order-estimate-toolbar-workflow', [
        'repairOrder' => $repairOrder,
        'isTerminal' => $isTerminal,
        'lifecycleOptions' => $lifecycleOptions,
        'closeVariantOptions' => $closeVariantOptions,
        'technicians' => $technicians,
        'soloOwnerShop' => $soloOwnerShop,
        'estimateVersion' => $estimateVersion,
        'financial' => $financial,
        'balanceProjection' => $balanceProjection ?? null,
        'mode' => 'edit',
    ])

    @include('operations.repair-orders.partials.repair-order-toolbar-mode-slot', [
        'repairOrder' => $repairOrder,
        'mode' => 'edit',
        'isTerminal' => $isTerminal,
        'registerModeShortcut' => false,
    ])
</div>
