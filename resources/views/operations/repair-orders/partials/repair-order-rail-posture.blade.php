{{-- Persistent Context: Financial · Approval · Communication · Workflow - existing posture only --}}
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
        'card card-sm' => ! $isDock,
    ])
    data-persistent-context="posture"
    data-posture-layout="{{ $postureLayout }}"
>
    <div @class(['card-body' => ! $isDock])>
        <div class="datagrid">
            <div class="datagrid-item">
                <div class="datagrid-title">Workflow</div>
                <div class="datagrid-content fw-medium">{{ $repairOrder->statusDisplayLabel() }}</div>
                <div class="small text-secondary">{{ $nextAction }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Approval</div>
                <div class="datagrid-content fw-medium">{{ $approvalPosture }}</div>
                <div class="small text-secondary">{{ $approvalHint }}</div>
                @unless ($isDock)
                    <div class="small text-secondary">
                        @if ($lastApprovalEvent)
                            Last authorization {{ $lastApprovalEvent->approved_at?->timezone(config('app.display_timezone'))->format('M j, g:i A') ?? 'time not recorded' }}
                        @else
                            No authorization recorded yet
                        @endif
                    </div>
                @endunless
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Communication</div>
                <div class="datagrid-content fw-medium">{{ $repairOrder->communicationNextAction() }}</div>
                <div class="small text-secondary">{{ $repairOrder->communicationPostureLabel() }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Financial</div>
                <div class="datagrid-content fw-medium">{{ $financialValue }}</div>
                <div class="small text-secondary">{{ $financialHint }}</div>
            </div>
            @if ($partsBlockingCount > 0)
                <div class="datagrid-item">
                    <div class="datagrid-title">Parts</div>
                    <div class="datagrid-content fw-medium text-orange">{{ $partsBlockingCount }} line{{ $partsBlockingCount === 1 ? '' : 's' }} blocking</div>
                    <div class="small text-secondary">{{ $repairOrder->procurementReadinessSummary() }}</div>
                </div>
            @endif
        </div>
    </div>
</div>
