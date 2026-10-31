@php
    $customerDocumentsCount = (int) ($customerDocumentsCount ?? 0);
    $canAuthorRepairOrder = (bool) ($canAuthorRepairOrder ?? false);
    $isTerminal = (bool) ($isTerminal ?? false);
    $showPaperwork = ($customerDocumentsCount > 0) || (! $isTerminal && $canAuthorRepairOrder);
    $paperworkLabel = $customerDocumentsCount > 0
        ? 'Paperwork ('.$customerDocumentsCount.')'
        : '+ Add Document';
@endphp

@if ($showPaperwork)
    <button
        type="button"
        class="btn"
        title="Scan, upload, or attach paperwork for this visit"
        data-workspace-modal-trigger="document"
        @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'document', invokeEl: $event.currentTarget } }))"
    >
        {{ $paperworkLabel }}
    </button>
@endif

@include('operations.repair-orders.partials.repair-order-estimate-print-menu', [
    'repairOrder' => $repairOrder,
    'financial' => $financial ?? null,
])
