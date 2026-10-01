@include('operations.repair-orders.partials.repair-order-orientation-header', [
    'repairOrder' => $repairOrder ?? null,
    'currentSituation' => $currentSituation ?? null,
    'workspaceStrip' => $workspaceStrip ?? null,
    'captureInPlace' => $captureInPlace ?? false,
])
