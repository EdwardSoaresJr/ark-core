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
    $workAuthorization = $repairOrder ? app(\App\Ark\Operations\RepairOrders\WorkCompletionAuthorization::class) : null;
    $approvedBeforeIncrease = $workAuthorization?->approvedSubtotalsBeforeIncrease($repairOrder) ?? [];
    $removedSinceApproval = $workAuthorization?->approvedLinesRemoved($repairOrder) ?? [];
    $pendingCentsFor = fn ($line): int => $workAuthorization
        ? $workAuthorization->pendingApprovalCents($repairOrder, $line)
        : (int) ($line->total_cents ?? 0);
    $needsAuthorizationCents = (int) $linesNeedingAuthorization->sum($pendingCentsFor);
    $hasAddedLines = $linesNeedingAuthorization->contains(fn ($line): bool => ! isset($approvedBeforeIncrease[(int) $line->id]));
    $hasCloseout = isset($closeout) && trim($closeout) !== '';
@endphp

<div
    {{ $attributes->class(['card']) }}
    @if ($hasCloseout)
        x-data="{ totalsTab: window.location.hash === '#financial-rail' ? 'closeout' : 'estimate' }"
        @ops-show-closeout.window="totalsTab = 'closeout'"
        @hashchange.window="if (window.location.hash === '#financial-rail') totalsTab = 'closeout'"
    @endif
>
    <div class="card-header">
        @if ($hasCloseout)
            <ul class="nav nav-tabs card-header-tabs" role="tablist" aria-label="Estimate money">
                <li class="nav-item" role="presentation">
                    <button
                        type="button"
                        class="nav-link"
                        role="tab"
                        data-totals-tab="estimate"
                        :class="{ active: totalsTab === 'estimate' }"
                        :aria-selected="totalsTab === 'estimate'"
                        @click="totalsTab = 'estimate'; if (window.location.hash === '#financial-rail') history.replaceState(null, '', window.location.pathname + window.location.search)"
                    >Estimate Total</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button
                        type="button"
                        class="nav-link"
                        role="tab"
                        data-totals-tab="closeout"
                        :class="{ active: totalsTab === 'closeout' }"
                        :aria-selected="totalsTab === 'closeout'"
                        @click="totalsTab = 'closeout'; history.replaceState(null, '', '#financial-rail')"
                    >Closeout</button>
                </li>
            </ul>
        @else
            <h3 class="card-title">Estimate Total</h3>
        @endif
    </div>

    <div @if ($hasCloseout) x-show="totalsTab === 'estimate'" @endif>
    <div id="authorized-money" data-continuity-region="authorized-money">
        @include('operations.repair-orders.partials.repair-order-approval-forecast', [
            'approvalForecast' => $approvalForecast,
        ])
    </div>

    <div id="estimate-totals-region" data-continuity-region="totals">
        <dl class="card-body mb-0">
            <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                <dt class="fw-normal text-secondary">Labor</dt>
                <dd class="mb-0 fw-semibold ark-money">{{ $totals->format($totals->laborCents()) }}</dd>
            </div>
            <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                <dt class="fw-normal text-secondary">Parts</dt>
                <dd class="mb-0 fw-semibold ark-money">{{ $totals->format($totals->partsCents()) }}</dd>
            </div>
            <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                <dt class="fw-normal text-secondary">Fees</dt>
                <dd class="mb-0 fw-semibold ark-money">{{ $totals->format($totals->feesCents()) }}</dd>
            </div>
            @if ($totals->standingDiscountCents() > 0)
                @php
                    $standingDiscountLabel = $repairOrder
                        ? \App\Ark\Operations\Financial\StandingDiscountPresentation::label(
                            $repairOrder->customer?->customer_type,
                            $totals->standingDiscountCents(),
                        ) ?? 'Discount'
                        : 'Discount';
                @endphp
                <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                    <dt class="fw-normal text-secondary">{{ $standingDiscountLabel }}</dt>
                    <dd class="mb-0 fw-semibold text-green ark-money">−{{ $totals->format($totals->standingDiscountCents()) }}</dd>
                </div>
            @endif
            <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                <dt class="fw-normal text-secondary">{{ $taxLabel ?? 'Tax' }}</dt>
                <dd class="mb-0 fw-semibold ark-money">{{ $totals->format($totals->taxCents()) }}</dd>
            </div>
            <div class="d-flex justify-content-between align-items-baseline gap-3 border-top pt-2 mt-1" data-total-row="final">
                <dt class="h3 mb-0">Total</dt>
                <dd class="h3 mb-0 ark-money">{{ $totals->format($totals->totalCents()) }}</dd>
            </div>
        </dl>
    </div>

    <div id="authorized-lines" data-continuity-region="authorized-money">
        @if ($linesNeedingAuthorization->isNotEmpty())
            <div class="card-body border-top bg-yellow-lt">
                <div class="d-flex justify-content-between align-items-baseline gap-3">
                    <span class="fw-semibold">Needs authorization</span>
                    <span class="fw-semibold ark-money">{{ $totals->format($needsAuthorizationCents) }}</span>
                </div>
                <ul class="list-unstyled small mt-1 mb-0">
                    @foreach ($linesNeedingAuthorization as $unauthorizedLine)
                        @php($approvedSubtotal = $approvedBeforeIncrease[(int) $unauthorizedLine->id] ?? null)
                        <li class="d-flex justify-content-between align-items-baseline gap-3" @if ($approvedSubtotal !== null) data-approved-line-increase="{{ $unauthorizedLine->id }}" @endif>
                            <span style="min-width: 0">
                                {{ $unauthorizedLine->description }}
                                @if ($approvedSubtotal !== null)
                                    <span class="d-block text-secondary">Increase · approved at {{ $totals->format($approvedSubtotal) }}</span>
                                @endif
                            </span>
                            <span class="ark-money">{{ $approvedSubtotal !== null ? '+' : '' }}{{ $totals->format($pendingCentsFor($unauthorizedLine)) }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($hasAddedLines)
                    <p class="small mt-1 mb-0">On the estimate. Not in approved sales until the customer approves these lines.</p>
                @endif
                @if ($approvedBeforeIncrease !== [])
                    <p class="small mt-1 mb-0">Approved lines priced above the customer's approval. Approved sales and the invoice count the approved amount until the customer approves the increase.</p>
                @endif
            </div>
        @endif
        @if ($removedSinceApproval !== [])
            <div class="card-body border-top" data-approved-lines-removed>
                <span class="fw-semibold">Removed after approval</span>
                <ul class="list-unstyled small mt-1 mb-0">
                    @foreach ($removedSinceApproval as $removedLine)
                        <li class="d-flex justify-content-between align-items-baseline gap-3">
                            <span class="text-decoration-line-through" style="min-width: 0">{{ $removedLine['description'] }}</span>
                            <span class="ark-money">{{ $totals->format($removedLine['subtotal_cents']) }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="small text-secondary mt-1 mb-0">Approved earlier and no longer on the estimate. The approval stays on file.</p>
            </div>
        @endif
    </div>

    <div id="estimate-balance-region" data-continuity-region="totals">
        @if ($showSuggestedDeposit || $showPreInvoiceSettlement || $showPostInvoiceSettlement)
            <dl class="card-body border-top mb-0">
                @if ($showSuggestedDeposit)
                    @if ($showDepositPartsRow)
                        <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                            <dt class="fw-normal text-secondary">Deposit · parts</dt>
                            <dd class="mb-0 fw-semibold ark-money">{{ $financial['suggestedDepositParts'] }}</dd>
                        </div>
                    @endif
                    @if ($showDepositDiagnosticsRow)
                        <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                            <dt class="fw-normal text-secondary">Deposit · diagnostics</dt>
                            <dd class="mb-0 fw-semibold ark-money">{{ $financial['suggestedDepositDiagnostics'] }}</dd>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                        <dt class="fw-normal text-secondary">
                            Suggested deposit
                            <span class="d-block small">Shop policy quote - not collected</span>
                        </dt>
                        <dd class="mb-0 d-flex align-items-center gap-2 fw-semibold ark-money">
                            <button
                                type="button"
                                class="btn btn-link btn-sm p-0"
                                data-ops-deposit-breakdown-open="{{ $depositBreakdownTemplateId }}"
                            >
                                Breakdown
                            </button>
                            <span data-suggested-deposit-amount="{{ $repairOrder->repair_order_id }}">{{ $financial['suggestedDeposit'] }}</span>
                        </dd>
                    </div>
                @endif
                @if ($showPreInvoiceSettlement)
                    <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                        <dt class="fw-normal text-secondary">Deposit on file</dt>
                        <dd class="mb-0 fw-semibold ark-money">−{{ $financial['unappliedDeposits'] }}</dd>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline gap-3 border-top pt-2 mt-1">
                        <dt>Balance Due</dt>
                        <dd class="mb-0 fw-bold ark-money">{{ $financial['projectedBalance'] ?? $financial['estimatedDueAtPickup'] }}</dd>
                    </div>
                @elseif ($showPostInvoiceSettlement)
                    @if ($depositsAppliedCents > 0)
                        <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                            <dt class="fw-normal text-secondary">Deposits</dt>
                            <dd class="mb-0 fw-semibold ark-money">−{{ $financial['depositsApplied'] }}</dd>
                        </div>
                    @endif
                    @if ($paymentsAppliedCents > 0)
                        <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                            <dt class="fw-normal text-secondary">Payments</dt>
                            <dd class="mb-0 fw-semibold text-green ark-money">−{{ $financial['paymentsApplied'] }}</dd>
                        </div>
                    @endif
                    @if ($creditsAppliedCents > 0)
                        <div class="d-flex justify-content-between align-items-baseline gap-3 py-1">
                            <dt class="fw-normal text-secondary">Store credit</dt>
                            <dd class="mb-0 fw-semibold ark-money">−{{ $financial['creditsApplied'] }}</dd>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between align-items-baseline gap-3 border-top pt-2 mt-1">
                        <dt>Balance Due</dt>
                        <dd class="mb-0 fw-bold ark-money">{{ $financial['projectedBalance'] ?? $financial['balanceDue'] }}</dd>
                    </div>
                @endif
            </dl>
        @endif
    </div>

    @if (trim($slot ?? '') !== '')
        <div class="card-body border-top">
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
