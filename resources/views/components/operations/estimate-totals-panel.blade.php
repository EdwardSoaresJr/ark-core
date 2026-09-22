@props([
    'totals',
    'taxLabel',
    'financial' => null,
    'repairOrder' => null,
    'approvalForecast' => null,
])

@php
    $hasFinancial = is_array($financial);
    $hasIssuedInvoice = $hasFinancial && ($financial['hasIssuedInvoice'] ?? false);
    $unappliedDepositsCents = $hasFinancial ? (int) ($financial['unappliedDepositsCents'] ?? 0) : 0;
    $depositsAppliedCents = $hasFinancial ? (int) ($financial['depositsAppliedCents'] ?? 0) : 0;
    $paymentsAppliedCents = $hasFinancial ? (int) ($financial['paymentsAppliedCents'] ?? 0) : 0;
    $creditsAppliedCents = $hasFinancial ? (int) ($financial['creditsAppliedCents'] ?? 0) : 0;
    $showSuggestedDeposit = $hasFinancial
        && ($financial['canRecordDeposit'] ?? false)
        && count($financial['suggestedDepositBreakdown'] ?? []) > 0;
    $depositBreakdownTemplateId = $repairOrder
        ? 'deposit-breakdown-template-'.$repairOrder->repair_order_id
        : null;
    $showDepositPartsRow = $showSuggestedDeposit
        && ($financial['suggestedDepositParts'] ?? null)
        && ($financial['suggestedDepositDiagnostics'] ?? null);
    $showDepositDiagnosticsRow = $showDepositPartsRow;
    $feesAndTaxCents = $totals->feesCents() + $totals->taxCents();
    $contractTotalLabel = $hasIssuedInvoice ? 'Invoice total' : 'Estimate total';
    $contractTotalValue = $hasIssuedInvoice
        ? ($financial['invoiceTotal'] ?? $totals->format($totals->totalCents()))
        : $totals->format($totals->totalCents());
    $balanceDueValue = $hasIssuedInvoice
        ? ($financial['projectedBalance'] ?? $financial['settlementBalanceDue'] ?? $financial['balanceDue'])
        : ($financial['projectedBalance'] ?? $financial['oweToday'] ?? $financial['estimatedDueAtPickup'] ?? $totals->format($totals->totalCents()));
    $balanceDueCents = $hasIssuedInvoice
        ? (int) ($financial['settlementBalanceDueCents'] ?? $financial['balanceDueCents'] ?? 0)
        : (int) ($financial['oweTodayCents'] ?? $financial['customerOwesTodayCents'] ?? 0);
    $isPaid = $hasFinancial && (bool) ($financial['isPaid'] ?? false);
    $collectedCents = $hasFinancial ? (int) ($financial['collectedCents'] ?? 0) : 0;
    $balanceState = match (true) {
        $isPaid => 'Paid',
        $hasIssuedInvoice && $collectedCents > 0 && $balanceDueCents > 0 => 'Partial',
        $balanceDueCents > 0 => 'Unpaid',
        $hasIssuedInvoice => 'Paid',
        default => null,
    };
@endphp

<div {{ $attributes->class(['ops-review-panel']) }}>
    <div class="ops-review-panel-header">
        <p class="ops-eyebrow">Financial summary</p>
    </div>

    @include('operations.repair-orders.partials.repair-order-approval-forecast', [
        'approvalForecast' => $approvalForecast,
    ])

    <dl class="divide-y divide-slate-100 px-3 py-1 text-sm">
        <div class="ops-total-row py-1.5">
            <dt class="text-slate-500">Labor</dt>
            <dd class="font-semibold tabular-nums text-slate-950">{{ $totals->format($totals->laborCents()) }}</dd>
        </div>
        <div class="ops-total-row py-1.5">
            <dt class="text-slate-500">Parts</dt>
            <dd class="font-semibold tabular-nums text-slate-950">{{ $totals->format($totals->partsCents()) }}</dd>
        </div>
        @if ($totals->standingDiscountCents() > 0)
            <div class="ops-total-row py-1.5">
                @php
                    $standingDiscountLabel = $repairOrder
                        ? \App\Ark\Operations\Financial\StandingDiscountPresentation::label(
                            $repairOrder->customer?->customer_type,
                            $totals->standingDiscountCents(),
                        ) ?? 'Discount'
                        : 'Discount';
                @endphp
                <dt class="text-slate-500">{{ $standingDiscountLabel }}</dt>
                <dd class="font-semibold tabular-nums text-emerald-700">−{{ $totals->format($totals->standingDiscountCents()) }}</dd>
            </div>
        @endif
        <div class="ops-total-row py-1.5">
            <dt class="text-slate-500">Fees + tax</dt>
            <dd class="font-semibold tabular-nums text-slate-950">{{ $totals->format($feesAndTaxCents) }}</dd>
        </div>
        <div class="ops-total-row ops-total-row--final py-2">
            <dt>{{ $contractTotalLabel }}</dt>
            <dd class="font-bold tabular-nums text-slate-950">{{ $contractTotalValue }}</dd>
        </div>
        @if ($showSuggestedDeposit)
            @if ($showDepositPartsRow)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Deposit · parts</dt>
                    <dd class="font-semibold tabular-nums text-slate-950">{{ $financial['suggestedDepositParts'] }}</dd>
                </div>
            @endif
            @if ($showDepositDiagnosticsRow)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Deposit · diagnostics</dt>
                    <dd class="font-semibold tabular-nums text-slate-950">{{ $financial['suggestedDepositDiagnostics'] }}</dd>
                </div>
            @endif
            <div class="ops-total-row py-1.5">
                <dt class="text-slate-500">
                    Suggested deposit
                    <span class="mt-0.5 block text-[10px] font-normal normal-case tracking-normal text-slate-400">Shop policy quote — not collected</span>
                </dt>
                <dd class="flex items-center justify-end gap-2 font-semibold tabular-nums text-slate-950">
                    <button
                        type="button"
                        class="shrink-0 border-0 bg-transparent p-0 text-[11px] font-semibold text-slate-600 underline decoration-slate-300 underline-offset-2 hover:text-slate-950"
                        data-ops-deposit-breakdown-open="{{ $depositBreakdownTemplateId }}"
                    >
                        Breakdown
                    </button>
                    <span data-suggested-deposit-amount="{{ $repairOrder->repair_order_id }}">{{ $financial['suggestedDeposit'] }}</span>
                </dd>
            </div>
        @endif
        @if ($hasFinancial)
            @if ($unappliedDepositsCents > 0 && ! $hasIssuedInvoice)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Deposit on file</dt>
                    <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['unappliedDeposits'] }}</dd>
                </div>
            @endif
            @if ($hasIssuedInvoice && $depositsAppliedCents > 0)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Deposits</dt>
                    <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['depositsApplied'] }}</dd>
                </div>
            @endif
            @if ($hasIssuedInvoice && $paymentsAppliedCents > 0)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Payments</dt>
                    <dd class="font-semibold tabular-nums text-emerald-800">−{{ $financial['paymentsApplied'] }}</dd>
                </div>
            @endif
            @if ($hasIssuedInvoice && $creditsAppliedCents > 0)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Store credit</dt>
                    <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['creditsApplied'] }}</dd>
                </div>
            @endif
            <div class="ops-total-row ops-total-row--due py-2">
                <dt>
                    Balance due
                    @if ($balanceState)
                        <span @class([
                            'mt-0.5 block text-[10px] font-bold uppercase tracking-[0.08em] normal-case',
                            'text-emerald-700' => $balanceState === 'Paid',
                            'text-amber-800' => $balanceState !== 'Paid',
                        ])>{{ $balanceState }}</span>
                    @endif
                </dt>
                <dd class="font-bold tabular-nums text-slate-950">{{ $balanceDueValue }}</dd>
            </div>
            @if ($financial['oweTodayDiffersFromSettlement'] ?? false)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Owe today</dt>
                    <dd class="font-semibold tabular-nums text-slate-700">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</dd>
                </div>
            @endif
        @endif
    </dl>
    @if (trim($slot ?? '') !== '')
        <div class="space-y-2 border-t border-slate-100 px-3 py-2">
            {{ $slot }}
        </div>
    @endif

    @if ($showSuggestedDeposit && $depositBreakdownTemplateId)
        @include('operations.repair-orders.partials.financial-suggested-deposit-template', [
            'financial' => $financial,
            'depositBreakdownTemplateId' => $depositBreakdownTemplateId,
            'repairOrder' => $repairOrder,
        ])
    @endif
</div>
