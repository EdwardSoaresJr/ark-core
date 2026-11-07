{{-- The RO editor's top bar actions. Paperwork is in the sticky footer (RepairOrderFooterProjection), so it is not repeated here. --}}
<div class="btn-list flex-nowrap" role="group" aria-label="Repair order commands" data-ro-command-actions>
    @unless ($isTerminal ?? false)
        @if ($canAuthorRepairOrder ?? false)
            <button
                type="button"
                class="btn"
                title="Review Estimate Notes"
                aria-label="Review Estimate Notes"
                @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'review-estimate-notes', context: {}, invokeEl: $event.currentTarget } }))"
            >
                <x-tabler.icon name="file-description" />
                <span class="ark-ro-visit__action-label">Review Notes</span>
            </button>
        @endif
    @endunless

    @include('operations.repair-orders.partials.repair-order-estimate-print-menu', [
        'repairOrder' => $repairOrder,
        'financial' => $financial ?? null,
    ])
</div>
