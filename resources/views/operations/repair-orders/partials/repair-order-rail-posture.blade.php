{{-- Persistent Context: Financial · Approval · Communication · Workflow — existing posture only --}}
@php
    $postureLayout = ($postureLayout ?? 'rail') === 'dock' ? 'dock' : 'rail';
    $isDock = $postureLayout === 'dock';
    $financialHint = ($financial['showFinancialRail'] ?? false)
        ? (($financial['hasIssuedInvoice'] ?? false)
            ? (
                ($financial['isPaid'] ?? false)
                    ? 'Settlement paid'
                    : 'Settlement balance '.($financial['settlementBalanceDue'] ?? $financial['balanceDue'])
            )
            : (
                (($financial['oweTodayCents'] ?? 0) > 0)
                    ? 'Owe today '.($financial['oweToday'] ?? $financial['projectedBalance'])
                    : $financial['workflowHint']
            ))
        : ($isDock ? 'Totals in the right rail.' : 'Totals stay in the panel below while you work.');
    $financialValue = ($financial['showFinancialRail'] ?? false)
        ? $financial['workflowLabel']
        : 'Estimate';
    $approvalHint = $approvedConcerns->count().' approved · '.$deferredConcerns->count().' deferred · '.$recommendedConcerns->count().' recommended';
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
    <div class="ops-review-rail-posture__band">
        <div class="ops-review-rail-posture__row">
            <p class="ops-review-rail-posture__label">Workflow</p>
            <p class="ops-review-rail-posture__value">{{ $repairOrder->statusDisplayLabel() }}</p>
            <p class="ops-review-rail-posture__hint">{{ $nextAction }}</p>
        </div>
        <div class="ops-review-rail-posture__row">
            <p class="ops-review-rail-posture__label">Approval</p>
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
            <p class="ops-review-rail-posture__label">Communication</p>
            <p class="ops-review-rail-posture__value">{{ $repairOrder->communicationNextAction() }}</p>
            <p class="ops-review-rail-posture__hint">{{ $repairOrder->communicationPostureLabel() }}</p>
        </div>
        <div class="ops-review-rail-posture__row">
            <p class="ops-review-rail-posture__label">Financial</p>
            <p class="ops-review-rail-posture__value">{{ $financialValue }}</p>
            <p class="ops-review-rail-posture__hint">{{ $financialHint }}</p>
        </div>
        @if ($partsBlockingCount > 0)
            <div class="ops-review-rail-posture__row ops-review-rail-posture__row--parts">
                <p class="ops-review-rail-posture__label">Parts</p>
                <p class="ops-review-rail-posture__value">{{ $partsBlockingCount }} line{{ $partsBlockingCount === 1 ? '' : 's' }} blocking</p>
                <p class="ops-review-rail-posture__hint">{{ $repairOrder->procurementReadinessSummary() }}</p>
            </div>
        @endif
    </div>
</div>
