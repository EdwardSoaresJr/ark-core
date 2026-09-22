{{-- Order status: workflow, authorization, and invoice as independent facts --}}
@php
    $postureLayout = ($postureLayout ?? 'rail') === 'dock' ? 'dock' : 'rail';
    $isDock = $postureLayout === 'dock';
    $approvalHint = $approvedConcerns->count().' approved · '.$deferredConcerns->count().' deferred · '.$recommendedConcerns->count().' recommended';
    $invoiceLabel = ($financial['showFinancialRail'] ?? false)
        ? ($financial['invoiceStatusLabel'] ?? 'Not issued')
        : 'Not issued';
    $invoiceHint = match (true) {
        ($financial['invoiceIssuedOutsideCloseout'] ?? false) => 'Invoice is issued while this order is still '.$repairOrder->statusDisplayLabel().'.',
        ($financial['canGenerateInvoice'] ?? false) => 'Ready for final invoice',
        ($financial['hasIssuedInvoice'] ?? false) && ($financial['isPaid'] ?? false) => 'Settlement paid',
        ($financial['hasIssuedInvoice'] ?? false) => 'Balance due '.($financial['settlementBalanceDue'] ?? $financial['balanceDue']),
        default => 'Final invoice issues at pickup',
    };
@endphp

<div
    @class([
        'ops-review-rail-posture',
        'ops-review-panel' => ! $isDock,
        'ops-review-rail-posture--dock' => $isDock,
    ])
    data-persistent-context="posture"
    data-posture-layout="{{ $postureLayout }}"
>
    <div class="ops-review-panel-header">
        <p class="ops-eyebrow">Order status</p>
    </div>
    <div class="ops-review-rail-posture__band">
        <div class="ops-review-rail-posture__row">
            <p class="ops-review-rail-posture__label">Workflow</p>
            <p class="ops-review-rail-posture__value">{{ $repairOrder->statusDisplayLabel() }}</p>
            <p class="ops-review-rail-posture__hint">{{ $nextAction }}</p>
        </div>
        <div class="ops-review-rail-posture__row">
            <p class="ops-review-rail-posture__label">Authorization</p>
            <p class="ops-review-rail-posture__value">{{ $approvalPosture }}</p>
            <p class="ops-review-rail-posture__hint">{{ $approvalHint }}</p>
            @unless ($isDock)
                <p class="ops-review-rail-posture__meta">
                    @if ($lastApprovalEvent)
                        Last authorization {{ $lastApprovalEvent->approved_at?->timezone(config('app.display_timezone'))->format('M j, g:i A') ?? 'time not recorded' }}
                    @else
                        No authorization recorded yet
                    @endif
                </p>
            @endunless
        </div>
        <div class="ops-review-rail-posture__row">
            <p class="ops-review-rail-posture__label">Invoice</p>
            <p class="ops-review-rail-posture__value">{{ $invoiceLabel }}</p>
            <p class="ops-review-rail-posture__hint">{{ $invoiceHint }}</p>
        </div>
    </div>
</div>
