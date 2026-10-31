@php
    $settlementBalanceDueCents = (int) ($financial['settlementBalanceDueCents'] ?? $financial['balanceDueCents'] ?? 0);
    $hasIssuedInvoice = (bool) ($financial['hasIssuedInvoice'] ?? false);
    $isPaid = (bool) ($financial['isPaid'] ?? ($hasIssuedInvoice && $settlementBalanceDueCents === 0));
    $hasSettlementDue = $hasIssuedInvoice && $settlementBalanceDueCents > 0;
    $oweTodayCents = (int) ($financial['oweTodayCents'] ?? $financial['customerOwesTodayCents'] ?? 0);
    $oweTodayDiffers = (bool) ($financial['oweTodayDiffersFromSettlement'] ?? false);
    $tone = match (true) {
        $hasSettlementDue => 'yellow',
        $hasIssuedInvoice && $isPaid => 'green',
        default => null,
    };
    $recentLedger = ($financial['ledgerEntries'] ?? collect())->reject(fn (array $entry): bool => $entry['isVoided'] ?? false)->take(3);
@endphp

<div id="financial-payment-panel" class="d-flex flex-column gap-2">
    <div id="financial-position" data-continuity-region="financial-position">
    @if ($hasIssuedInvoice)
        <div @class(['d-flex justify-content-between align-items-start gap-3 position-relative', 'ps-3' => $tone])>
            @if ($tone)
                <span class="card-status-start bg-{{ $tone }}"></span>
            @endif
            <div style="min-width: 0">
                <div class="subheader">
                    @if (($financial['waivedCents'] ?? 0) > 0)
                        Would have cost
                    @else
                        Settlement balance
                    @endif
                </div>
                <div class="h2 mb-0 ark-money">
                    @if (($financial['waivedCents'] ?? 0) > 0)
                        {{ $financial['wouldHaveCost'] }}
                    @else
                        {{ $financial['settlementBalanceDue'] ?? $financial['balanceDue'] }}
                    @endif
                </div>
                @if ($oweTodayDiffers)
                    <div class="subheader mt-1">Owe today</div>
                    <div class="fw-bold ark-money">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</div>
                @endif
            </div>
            <div class="text-end small">
                @if (($financial['waivedCents'] ?? 0) > 0)
                    <div>Collected {{ $financial['collected'] }}</div>
                    <div class="text-orange">Waived {{ $financial['waived'] }} · {{ $financial['collectionDispositionLabel'] }}</div>
                    @if ($settlementBalanceDueCents > 0)
                        <div class="fw-bold">Settlement due {{ $financial['settlementBalanceDue'] ?? $financial['balanceDue'] }}</div>
                    @endif
                @else
                    <div>Invoice {{ $financial['invoiceTotal'] }}</div>
                    @if (($financial['paymentsApplied'] ?? '$0.00') !== '$0.00')
                        <div class="text-green">Paid {{ $financial['paymentsApplied'] }}</div>
                    @endif
                @endif
            </div>
        </div>
    @elseif (($financial['unappliedDepositsCents'] ?? 0) > 0)
        <div @class(['d-flex justify-content-between align-items-start gap-3 position-relative', 'ps-3' => $tone])>
            @if ($tone)
                <span class="card-status-start bg-{{ $tone }}"></span>
            @endif
            <div style="min-width: 0">
                <div class="subheader">Owe today</div>
                <div class="h2 mb-0 ark-money">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</div>
            </div>
            <div class="text-end small">
                <div>Deposits {{ $financial['unappliedDeposits'] }}</div>
                <div>Invoice not issued</div>
            </div>
        </div>
        <p class="small mt-2 mb-0">
            Issue the final invoice at pickup to lock settlement. The customer can still pay more toward the remaining balance now.
        </p>
    @elseif ($oweTodayCents > 0)
        <div @class(['d-flex justify-content-between align-items-start gap-3 position-relative', 'ps-3' => $tone])>
            @if ($tone)
                <span class="card-status-start bg-{{ $tone }}"></span>
            @endif
            <div style="min-width: 0">
                <div class="subheader">Owe today</div>
                <div class="h2 mb-0 ark-money">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</div>
            </div>
            <div class="text-end small">
                <div>Invoice not issued</div>
            </div>
        </div>
        <p class="small mt-2 mb-0">{{ $financial['workflowHint'] }}</p>
    @else
        <p class="small mb-0">{{ $financial['workflowHint'] }}</p>
    @endif
    </div>

    @error('invoice')
        <p class="small text-red mb-0">{{ $message }}</p>
    @enderror

    @if ($financial['invoiceBlockingReason'] ?? null)
        <p class="small text-orange mb-0">{{ $financial['invoiceBlockingReason'] }}</p>
    @endif

    @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
        <div id="invoice-eligibility" data-continuity-region="settlement">
            @if ($financial['canGenerateInvoice'] ?? false)
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.invoice.store', $repairOrder) }}"
                    data-refresh-scope="rail"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <button type="submit" class="btn btn-primary w-100">
                        Generate Final Invoice
                    </button>
                </form>
            @endif
        </div>

        @if (($financial['canRecordDeposit'] ?? false) && ! ($financial['canRecordManualDeposit'] ?? false) && ($financial['suggestedDepositSatisfied'] ?? false))
            <div id="settlement-deposit" data-continuity-region="settlement">
                <div class="alert alert-success mb-0">
                    <div class="fw-bold">Deposit on file</div>
                    <div>Remaining balance is already covered. Void a payment history entry if one was recorded in error.</div>
                </div>
            </div>
        @endif

        @if (($financial['canRecordManualDeposit'] ?? false) || ($financial['canRecordPayment'] ?? false) || ($financial['canTakePaymentCapture'] ?? false))
            @include('operations.repair-orders.partials.financial-payment-workspace', [
                'repairOrder' => $repairOrder,
                'financial' => $financial,
                'estimateVersion' => $estimateVersion,
            ])
        @endif

        @if ($financial['canWaiveBalance'] ?? false)
            <a href="#waive-balance" class="btn btn-outline-warning w-100" @click.prevent="$dispatch('ops-show-closeout'); $nextTick(() => document.getElementById('waive-balance')?.scrollIntoView({ block: 'start' }))">
                Waive balance
            </a>
        @endif
    @endcan

    <div id="settlement-history" data-continuity-region="settlement">
        @if ($recentLedger->isNotEmpty())
            <div class="subheader">Recent payments</div>
            <div class="list-group list-group-flush">
                @foreach ($recentLedger as $entry)
                    <div class="list-group-item px-0 py-1 d-flex justify-content-between align-items-start gap-3 small">
                        <div style="min-width: 0">
                            <div class="fw-bold">
                                {{ $entry['typeLabel'] }}
                                @if ($entry['method'])
                                    · {{ $entry['method'] }}
                                @endif
                            </div>
                            <div class="text-secondary">{{ $entry['recordedAt'] ?? 'Pending' }}</div>
                        </div>
                        <div class="fw-bold ark-money">{{ $entry['amount'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <a href="#financial-rail" class="btn btn-ghost-secondary w-100" @click="$dispatch('ops-show-closeout')">
        Full ledger &amp; closeout
    </a>
</div>
