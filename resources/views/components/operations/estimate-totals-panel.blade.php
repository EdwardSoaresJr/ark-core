@props([
    'totals',
    'taxLabel',
    'financial' => null,
    'repairOrder' => null,
    'approvalForecast' => null,
    'linesNeedingAuthorization' => null,
])

@php
    $hasFinancial = is_array($financial);
    $hasIssuedInvoice = $hasFinancial && ($financial['hasIssuedInvoice'] ?? false);
    $unappliedDepositsCents = $hasFinancial ? (int) ($financial['unappliedDepositsCents'] ?? 0) : 0;
    $depositsAppliedCents = $hasFinancial ? (int) ($financial['depositsAppliedCents'] ?? 0) : 0;
    $paymentsAppliedCents = $hasFinancial ? (int) ($financial['paymentsAppliedCents'] ?? 0) : 0;
    $creditsAppliedCents = $hasFinancial ? (int) ($financial['creditsAppliedCents'] ?? 0) : 0;
    $showPreInvoiceSettlement = $hasFinancial && ! $hasIssuedInvoice && $unappliedDepositsCents > 0;
    $showPostInvoiceSettlement = $hasFinancial && $hasIssuedInvoice;
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
    $linesNeedingAuthorization = collect($linesNeedingAuthorization ?? []);
    $needsAuthorizationCents = (int) $linesNeedingAuthorization->sum(
        fn ($line): int => (int) ($line->total_cents ?? 0),
    );
    $hasCloseout = isset($closeout) && trim($closeout) !== '';
@endphp

<div
    {{ $attributes->class(['ops-review-panel']) }}
    @if ($hasCloseout)
        x-data="{ totalsTab: window.location.hash === '#financial-rail' ? 'closeout' : 'estimate' }"
        @ops-show-closeout.window="totalsTab = 'closeout'"
        @hashchange.window="if (window.location.hash === '#financial-rail') totalsTab = 'closeout'"
    @endif
>
    @if ($hasCloseout)
        <div class="ops-ro-workspace-tabs__nav ops-totals-tabs" role="tablist" aria-label="Estimate money">
            <button
                type="button"
                class="ops-ro-workspace-tab"
                role="tab"
                :class="{ 'ops-ro-workspace-tab--active': totalsTab === 'estimate' }"
                :aria-selected="totalsTab === 'estimate'"
                @click="totalsTab = 'estimate'; if (window.location.hash === '#financial-rail') history.replaceState(null, '', window.location.pathname + window.location.search)"
            >Estimate Total</button>
            <button
                type="button"
                class="ops-ro-workspace-tab"
                role="tab"
                :class="{ 'ops-ro-workspace-tab--active': totalsTab === 'closeout' }"
                :aria-selected="totalsTab === 'closeout'"
                @click="totalsTab = 'closeout'; history.replaceState(null, '', '#financial-rail')"
            >Closeout</button>
        </div>
    @else
        <div class="ops-review-panel-header">
            <p class="ops-eyebrow">Estimate Total</p>
        </div>
    @endif

    <div @if ($hasCloseout) x-show="totalsTab === 'estimate'" @endif>
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
        <div class="ops-total-row py-1.5">
            <dt class="text-slate-500">Fees</dt>
            <dd class="font-semibold tabular-nums text-slate-950">{{ $totals->format($totals->feesCents()) }}</dd>
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
            <dt class="text-slate-500">{{ $taxLabel ?? 'Tax' }}</dt>
            <dd class="font-semibold tabular-nums text-slate-950">{{ $totals->format($totals->taxCents()) }}</dd>
        </div>
        <div class="ops-total-row ops-total-row--final py-2">
            <dt>Total</dt>
            <dd class="font-bold tabular-nums text-slate-950">{{ $totals->format($totals->totalCents()) }}</dd>
        </div>
        @if ($linesNeedingAuthorization->isNotEmpty())
            <div class="py-2">
                <div class="ops-total-row">
                    <dt class="text-amber-950">Needs authorization</dt>
                    <dd class="font-semibold tabular-nums text-amber-950">{{ $totals->format($needsAuthorizationCents) }}</dd>
                </div>
                <ul class="mt-1 space-y-0.5 text-xs leading-4 text-amber-950">
                    @foreach ($linesNeedingAuthorization as $unauthorizedLine)
                        <li class="flex items-baseline justify-between gap-3">
                            <span class="min-w-0">{{ $unauthorizedLine->description }}</span>
                            <span class="shrink-0 tabular-nums">{{ $totals->format((int) $unauthorizedLine->total_cents) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-1 text-[11px] leading-4 text-slate-500">On the estimate. Not in approved sales until the customer approves these lines.</p>
            </div>
        @endif
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
                    <span class="mt-0.5 block text-[10px] font-normal normal-case tracking-normal text-slate-400">Shop policy quote - not collected</span>
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
        @if ($showPreInvoiceSettlement)
            <div class="ops-total-row py-1.5">
                <dt class="text-slate-500">Deposit on file</dt>
                <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['unappliedDeposits'] }}</dd>
            </div>
            <div class="ops-total-row ops-total-row--due py-2">
                <dt>Balance Due</dt>
                <dd class="font-bold tabular-nums text-slate-950">{{ $financial['projectedBalance'] ?? $financial['estimatedDueAtPickup'] }}</dd>
            </div>
        @elseif ($showPostInvoiceSettlement)
            @if ($depositsAppliedCents > 0)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Deposits</dt>
                    <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['depositsApplied'] }}</dd>
                </div>
            @endif
            @if ($paymentsAppliedCents > 0)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Payments</dt>
                    <dd class="font-semibold tabular-nums text-emerald-800">−{{ $financial['paymentsApplied'] }}</dd>
                </div>
            @endif
            @if ($creditsAppliedCents > 0)
                <div class="ops-total-row py-1.5">
                    <dt class="text-slate-500">Store credit</dt>
                    <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['creditsApplied'] }}</dd>
                </div>
            @endif
            <div class="ops-total-row ops-total-row--due py-2">
                <dt>Balance Due</dt>
                <dd class="font-bold tabular-nums text-slate-950">{{ $financial['projectedBalance'] ?? $financial['balanceDue'] }}</dd>
            </div>
        @endif
    </dl>
    @if (trim($slot ?? '') !== '')
        <div class="space-y-2 border-t border-slate-100 px-3 py-2">
            {{ $slot }}
        </div>
    @endif
    </div>

    @if ($hasCloseout)
        <div x-show="totalsTab === 'closeout'" x-cloak>
            {{ $closeout }}
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
