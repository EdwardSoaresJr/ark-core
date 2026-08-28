@php
    $concernUsesRepairActions = $concern->usesRepairActions();
    $reviewLinePresenter = app(\App\Ark\Operations\RepairOrders\EstimateReviewLinePresenter::class);
    $ungroupedLines = \App\Ark\Operations\RepairOrders\RepairOrderLineWorksheetOrder::sort(
        $concern->lines->filter(fn ($line) => $line->repair_order_work_group_id === null && $line->shouldDisplayOnEstimateWorksheet())
    );
    $displayLines = $concernUsesRepairActions
        ? $ungroupedLines
        : \App\Ark\Operations\RepairOrders\RepairOrderLineWorksheetOrder::sort(
            $concern->lines->filter(fn ($line) => $line->shouldDisplayOnEstimateWorksheet())
        );
@endphp

@if ($concern->workGroups->isNotEmpty() || $displayLines->isNotEmpty())
    <div class="ops-review-lines-head hidden md:grid">
        <span aria-hidden="true"></span>
        <span class="text-right">Qty</span>
        <span class="text-right">Price</span>
        <span class="text-right">Subtotal</span>
        <span class="text-right">Fees</span>
        <span class="text-right">Tax</span>
        <span class="text-right">Total</span>
    </div>
    <div class="grid grid-cols-3 gap-2 border-b border-slate-200 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 md:hidden">
        <span class="text-right">Qty</span>
        <span class="text-right">Price</span>
        <span class="text-right">Total</span>
    </div>
@endif

<div class="ops-review-lines">
    @foreach ($concern->workGroups as $workGroup)
        @php
            $workGroupLines = \App\Ark\Operations\RepairOrders\RepairOrderLineWorksheetOrder::sort(
                $workGroup->lines->filter(fn ($line) => $line->shouldDisplayOnEstimateWorksheet())
            );
        @endphp
        @if ($workGroupLines->isNotEmpty())
            @php
                $reviewStatus = $workGroup->status instanceof \App\Ark\Operations\RepairOrders\RepairActionStatus
                    ? $workGroup->status
                    : \App\Ark\Operations\RepairOrders\RepairActionStatus::Pending;
            @endphp
            <div class="ops-review-repair-action-block">
                <p class="ops-review-repair-action">{{ $workGroup->title }}</p>
                <p class="px-3 pb-1 text-[11px] text-slate-500">
                    @if ($workGroup->ownerUser)
                        Owner · {{ $workGroup->ownerUser->name }} ·
                    @endif
                    {{ $reviewStatus->label() }}
                    @if ($workGroup->updated_at)
                        · Updated {{ $workGroup->updated_at->timezone(config('app.timezone'))->format('g:i A') }}
                    @endif
                </p>
                @if (filled($workGroup->latest_update))
                    <div class="mx-3 mb-2 rounded-sm border border-slate-200 bg-slate-50 px-2.5 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Update</p>
                        <p class="mt-0.5 whitespace-pre-wrap text-sm text-slate-800">{{ $workGroup->latest_update }}</p>
                    </div>
                @endif
            </div>
            @foreach ($workGroupLines as $line)
                @include('operations.repair-orders._line-composition', [
                    'line' => $line,
                    'displayDescription' => $reviewLinePresenter->description($line, $concern, $workGroup),
                    'repairOrder' => $repairOrder,
                    'totals' => $totals,
                    'taxLabel' => $taxLabel,
                    'isTerminal' => $isTerminal,
                    'partStateOptions' => $line->availableProcurementTransitions(),
                    'showActions' => false,
                    'showProcurement' => true,
                    'estimateVersion' => $estimateVersion,
                    'lineGrid' => 'review',
                ])
            @endforeach
        @endif
    @endforeach

    @if ($concernUsesRepairActions && $displayLines->isNotEmpty())
        <p class="ops-review-repair-action">Standalone scope lines</p>
    @endif

    @foreach ($displayLines as $line)
        @include('operations.repair-orders._line-composition', [
            'line' => $line,
            'displayDescription' => $reviewLinePresenter->description($line, $concern),
            'repairOrder' => $repairOrder,
            'totals' => $totals,
            'taxLabel' => $taxLabel,
            'isTerminal' => $isTerminal,
            'partStateOptions' => $line->availableProcurementTransitions(),
            'showActions' => false,
            'showProcurement' => true,
            'estimateVersion' => $estimateVersion,
            'lineGrid' => 'review',
        ])
    @endforeach
</div>
