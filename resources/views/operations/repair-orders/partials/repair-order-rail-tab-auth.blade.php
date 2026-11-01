<div id="authorization-rail-panel" data-authorization-panel>
    @php
        use App\Ark\Operations\Financial\EstimateTotalsCalculator;

        $approvedTotals = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder);
    @endphp

    <div class="card-body pb-0">
        <h3 class="card-title mb-0">Customer Authorization</h3>
    </div>

    @include('operations.repair-orders.partials.authorization-record-form', [
        'repairOrder' => $repairOrder,
        'totals' => $totals,
        'approvedTotals' => $approvedTotals,
        'isTerminal' => $isTerminal,
        'estimateVersion' => $estimateVersion,
    ])

    <div class="card-body border-top pb-2">
        <div class="subheader">Authorization History</div>
    </div>
    <div class="list-group list-group-flush" data-authorization-history>
        @forelse ($repairOrder->approvalEvents as $approvalEvent)
            @php
                $revocation = $approvalEvent->revocation;
                $approvalHeadline = App\Ark\Operations\Approvals\ApprovalEventStaffPresentation::headline($approvalEvent);
                $recordedAmountCents = App\Ark\Operations\Approvals\ApprovalEventStaffPresentation::recordedAmountCents($approvalEvent);
            @endphp
            <div class="list-group-item">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div style="min-width: 0">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-bold">{{ $approvalHeadline }}</span>
                            @if ($revocation)
                                <span class="badge bg-yellow-lt">Revoked</span>
                            @endif
                        </div>
                        <div class="small text-secondary">
                            {{ $approvalEvent->source->label() }} · {{ $approvalEvent->approved_by ?: 'Customer' }} · {{ $approvalEvent->approved_at?->timezone(config('app.display_timezone'))->format('M j, g:i A') ?? 'time not recorded' }}
                        </div>
                    </div>
                    <div class="flex-shrink-0 text-end small">
                        @if ($recordedAmountCents > 0)
                            <span class="d-block fw-bold ark-money">{{ $approvedTotals->format($recordedAmountCents) }}</span>
                        @else
                            <span class="d-block text-uppercase text-secondary">{{ App\Ark\Operations\Approvals\ApprovalEventStaffPresentation::amountLabel($approvalEvent) }}</span>
                        @endif
                    </div>
                </div>
                @if ($approvalEvent->notes)
                    <div class="small text-secondary mt-1">{{ $approvalEvent->notes }}</div>
                @endif
                @if ($revocation)
                    <div class="small text-orange mt-2">
                        Revoked via {{ $revocation->source->label() }} · {{ $revocation->revoked_by }}
                        · {{ $revocation->revoked_at?->timezone(config('app.display_timezone'))->format('M j, g:i A') ?? 'time not recorded' }}
                        @if ($revocation->recordedBy)
                            · recorded by {{ $revocation->recordedBy->name }}
                        @endif
                    </div>
                    @if ($revocation->notes)
                        <div class="small text-secondary mt-1">{{ $revocation->notes }}</div>
                    @endif
                @elseif (! ($isTerminal ?? false))
                    @include('operations.repair-orders.partials.authorization-revoke-form', [
                        'repairOrder' => $repairOrder,
                        'approvalEvent' => $approvalEvent,
                        'estimateVersion' => $estimateVersion,
                        'isTerminal' => $isTerminal,
                    ])
                @endif
            </div>
        @empty
            <div class="list-group-item small text-secondary">
                No customer approval has been recorded yet. Record approval once for the recommended work on this estimate.
            </div>
        @endforelse
    </div>
</div>
