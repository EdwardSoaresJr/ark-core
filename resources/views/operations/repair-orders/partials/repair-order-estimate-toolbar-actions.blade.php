{{-- Add Work, PartsTech, other parts catalogs, labor guides and the PartsTech cart live in the docked RO footer. --}}
@php
    $mode = $mode ?? 'edit';
    $isTerminal = $isTerminal ?? false;
    $showCaptureDealerQuote = $mode === 'edit' && ! $isTerminal;
    // The customer's estimate as they see it, for staff: shown on locked and closed estimates too.
    $showEstimatePreview = isset($repairOrder)
        && (auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value) ?? false)
        && $repairOrder->lines()->exists();
    $showCustomerView = $mode === 'edit' && ! $isTerminal;
@endphp

@if ($showCustomerView || $showEstimatePreview)
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3" role="toolbar" aria-label="Estimate tools" data-estimate-tools>
        <div class="d-flex flex-wrap align-items-start gap-2">
            @if ($showCaptureDealerQuote)
                <button
                    type="button"
                    class="btn"
                    @click="$dispatch('ark:dealer-quote-capture-open')"
                    title="Import a dealer quote PDF or pasted text onto this estimate"
                >
                    Import
                </button>
            @endif
        </div>

        <div class="d-flex flex-wrap align-items-start gap-2">
            @if ($showEstimatePreview)
                <a
                    href="{{ route('operations.repair-orders.portal-preview', $repairOrder) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn"
                    title="Open the estimate as the customer sees it. Nothing is sent, approved, or recorded."
                    data-estimate-preview
                >
                    Preview
                </a>
            @endif
            @if ($showCustomerView)
                <button
                    type="button"
                    class="btn btn-ghost-secondary"
                    :class="estimateContext === 'portal' ? 'active' : ''"
                    :aria-pressed="estimateContext === 'portal'"
                    @click="toggleEstimateContext('portal')"
                >
                    Customer View
                </button>
            @endif
        </div>
    </div>
@endif
