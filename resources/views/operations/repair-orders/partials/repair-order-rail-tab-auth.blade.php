<div id="authorization-rail" class="ops-review-rail-tab-panel divide-y divide-slate-100 text-sm">
    @php
        use App\Ark\Operations\Financial\EstimateTotalsCalculator;

        $approvedTotals = app(EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder);
    @endphp

    <div class="ops-review-panel-header">
        <p class="ops-eyebrow">Customer Authorization</p>
    </div>

    @include('operations.repair-orders.partials.authorization-record-form', [
        'repairOrder' => $repairOrder,
        'totals' => $totals,
        'approvedTotals' => $approvedTotals,
        'isTerminal' => $isTerminal,
        'estimateVersion' => $estimateVersion,
    ])

    <div class="ops-review-panel-header border-t border-slate-200">
        <p class="ops-eyebrow">Authorization History</p>
    </div>
    <div class="divide-y divide-slate-100">
        @forelse ($repairOrder->approvalEvents as $approvalEvent)
            @php
                $revocation = $approvalEvent->revocation;
                $approvalHeadline = App\Ark\Operations\Approvals\ApprovalEventStaffPresentation::headline($approvalEvent);
            @endphp
            <div class="px-3 py-2">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-bold text-slate-950">{{ $approvalHeadline }}</p>
                            @if ($revocation)
                                <span class="rounded-sm border border-amber-300 bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.08em] text-amber-900">Revoked</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs leading-4 text-slate-500">
                            {{ $approvalEvent->source->label() }} · {{ $approvalEvent->approved_by ?: 'Customer' }} · {{ $approvalEvent->approved_at?->timezone(config('app.display_timezone'))->format('M j, g:i A') ?? 'time not recorded' }}
                        </p>
                    </div>
                    <p class="shrink-0 text-right text-xs font-bold text-slate-700">
                        @if ($approvedTotals->totalCents() > 0)
                            <span class="block font-black tabular-nums text-slate-950">{{ $approvedTotals->format($approvedTotals->totalCents()) }}</span>
                        @else
                            <span class="block uppercase tracking-[0.08em] text-slate-500">No approved work</span>
                        @endif
                    </p>
                </div>
                @if ($approvalEvent->notes)
                    <p class="mt-1 text-xs leading-4 text-slate-600">{{ $approvalEvent->notes }}</p>
                @endif
                @if ($revocation)
                    <p class="mt-2 text-xs leading-4 text-amber-900">
                        Revoked via {{ $revocation->source->label() }} · {{ $revocation->revoked_by }}
                        · {{ $revocation->revoked_at?->timezone(config('app.display_timezone'))->format('M j, g:i A') ?? 'time not recorded' }}
                        @if ($revocation->recordedBy)
                            · recorded by {{ $revocation->recordedBy->name }}
                        @endif
                    </p>
                    @if ($revocation->notes)
                        <p class="mt-1 text-xs leading-4 text-amber-800">{{ $revocation->notes }}</p>
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
            <div class="px-3 py-2 text-xs leading-4 text-slate-500">
                No customer approval has been recorded yet. Set each concern to Approved or Declined, then record how they said yes.
            </div>
        @endforelse
    </div>
</div>
