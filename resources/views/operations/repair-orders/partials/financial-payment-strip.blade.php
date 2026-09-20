@php
    $settlementBalanceDueCents = (int) ($financial['settlementBalanceDueCents'] ?? $financial['balanceDueCents'] ?? 0);
    $hasIssuedInvoice = (bool) ($financial['hasIssuedInvoice'] ?? false);
    $isPaid = (bool) ($financial['isPaid'] ?? ($hasIssuedInvoice && $settlementBalanceDueCents === 0));
    $hasSettlementDue = $hasIssuedInvoice && $settlementBalanceDueCents > 0;
    $oweTodayCents = (int) ($financial['oweTodayCents'] ?? $financial['customerOwesTodayCents'] ?? 0);
    $oweTodayDiffers = (bool) ($financial['oweTodayDiffersFromSettlement'] ?? false);
    $tone = match (true) {
        $hasSettlementDue => 'ops-financial-payment-strip--due',
        $hasIssuedInvoice && $isPaid => 'ops-financial-payment-strip--paid',
        ($financial['unappliedDepositsCents'] ?? 0) > 0 => 'ops-financial-payment-strip--deposit',
        default => '',
    };
    $recentLedger = ($financial['ledgerEntries'] ?? collect())->reject(fn (array $entry): bool => $entry['isVoided'] ?? false)->take(3);
@endphp

<div id="financial-payment-panel" class="ops-financial-payment-strip {{ $tone }}">
    @if ($hasIssuedInvoice)
        <div class="ops-financial-payment-strip__balance">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                    @if (($financial['waivedCents'] ?? 0) > 0)
                        Would have cost
                    @else
                        Settlement balance
                    @endif
                </p>
                <p class="mt-0.5 text-2xl font-black tabular-nums text-slate-950">
                    @if (($financial['waivedCents'] ?? 0) > 0)
                        {{ $financial['wouldHaveCost'] }}
                    @else
                        {{ $financial['settlementBalanceDue'] ?? $financial['balanceDue'] }}
                    @endif
                </p>
                @if ($oweTodayDiffers)
                    <p class="mt-1 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Owe today</p>
                    <p class="text-sm font-bold tabular-nums text-slate-700">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</p>
                @endif
            </div>
            <div class="shrink-0 text-right text-xs font-semibold leading-4 text-slate-600">
                @if (($financial['waivedCents'] ?? 0) > 0)
                    <p>Collected {{ $financial['collected'] }}</p>
                    <p class="text-amber-900">Waived {{ $financial['waived'] }} · {{ $financial['collectionDispositionLabel'] }}</p>
                    @if ($settlementBalanceDueCents > 0)
                        <p class="font-bold text-slate-950">Settlement due {{ $financial['settlementBalanceDue'] ?? $financial['balanceDue'] }}</p>
                    @endif
                @else
                    <p>Invoice {{ $financial['invoiceTotal'] }}</p>
                    @if (($financial['paymentsApplied'] ?? '$0.00') !== '$0.00')
                        <p class="text-emerald-800">Paid {{ $financial['paymentsApplied'] }}</p>
                    @endif
                @endif
            </div>
        </div>
    @elseif (($financial['unappliedDepositsCents'] ?? 0) > 0)
        <div class="ops-financial-payment-strip__balance">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Owe today</p>
                <p class="mt-0.5 text-2xl font-black tabular-nums text-slate-950">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</p>
            </div>
            <div class="shrink-0 text-right text-xs font-semibold leading-4 text-slate-600">
                <p>Deposits {{ $financial['unappliedDeposits'] }}</p>
                <p>Invoice not issued</p>
            </div>
        </div>
        <div class="ops-financial-payment-strip__hint">
            <p class="text-xs font-semibold leading-4 text-slate-700">
                Issue the final invoice at pickup to lock settlement. The customer can still pay more toward the remaining balance now.
            </p>
        </div>
    @elseif ($oweTodayCents > 0)
        <div class="ops-financial-payment-strip__balance">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Owe today</p>
                <p class="mt-0.5 text-2xl font-black tabular-nums text-slate-950">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</p>
            </div>
            <div class="shrink-0 text-right text-xs font-semibold leading-4 text-slate-600">
                <p>Invoice not issued</p>
            </div>
        </div>
        <div class="ops-financial-payment-strip__hint">
            <p class="text-xs font-semibold leading-4 text-slate-700">{{ $financial['workflowHint'] }}</p>
        </div>
    @else
        <div class="ops-financial-payment-strip__hint">
            <p class="text-xs font-semibold leading-4 text-slate-700">{{ $financial['workflowHint'] }}</p>
        </div>
    @endif

    @error('invoice')
        <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
    @enderror

    @if ($financial['invoiceBlockingReason'] ?? null)
        <p class="text-xs font-semibold leading-4 text-amber-900">{{ $financial['invoiceBlockingReason'] }}</p>
    @endif

    @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
        @if ($financial['canGenerateInvoice'] ?? false)
            <form
                method="POST"
                action="{{ route('operations.repair-orders.invoice.store', $repairOrder) }}"
                data-refresh-scope="rail"
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-sm bg-slate-950 px-3 text-xs font-bold text-white hover:bg-slate-800">
                    Generate Final Invoice
                </button>
            </form>
        @endif

        @if ($financial['canRecordDeposit'] ?? false)
            @if ($financial['canRecordManualDeposit'] ?? false)
                @include('operations.repair-orders.partials.financial-record-deposit', [
                    'repairOrder' => $repairOrder,
                    'financial' => $financial,
                    'estimateVersion' => $estimateVersion,
                ])
            @elseif ($financial['suggestedDepositSatisfied'] ?? false)
                <div class="border border-emerald-200 bg-emerald-50/70 p-3 text-xs leading-5 text-emerald-950">
                    <p class="font-bold">Deposit on file</p>
                    <p class="mt-1">Remaining balance is already covered. Void a payment history entry if one was recorded in error.</p>
                </div>
            @endif
        @endif

        @if ($financial['canRecordPayment'] ?? false)
            @include('operations.repair-orders.partials.financial-record-payment', [
                'repairOrder' => $repairOrder,
                'financial' => $financial,
                'estimateVersion' => $estimateVersion,
            ])
        @endif

        @include('operations.repair-orders.partials.financial-take-payment', [
            'repairOrder' => $repairOrder,
            'financial' => $financial,
            'estimateVersion' => $estimateVersion,
        ])

        @if ($financial['canWaiveBalance'] ?? false)
            <a
                href="#waive-balance"
                class="inline-flex min-h-10 w-full items-center justify-center rounded-sm border border-amber-300 bg-amber-50 px-3 text-xs font-bold text-amber-950 hover:border-amber-400"
            >
                Waive balance
            </a>
        @endif
    @endcan

    @if ($recentLedger->isNotEmpty())
        <div class="ops-financial-payment-strip__history">
            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Recent payments</p>
            <div class="mt-1 divide-y divide-slate-100">
                @foreach ($recentLedger as $entry)
                    <div class="flex items-start justify-between gap-3 py-1.5 text-xs">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900">
                                {{ $entry['typeLabel'] }}
                                @if ($entry['method'])
                                    · {{ $entry['method'] }}
                                @endif
                            </p>
                            <p class="text-slate-500">{{ $entry['recordedAt'] ?? 'Pending' }}</p>
                        </div>
                        <p class="shrink-0 font-black tabular-nums text-slate-950">{{ $entry['amount'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <a href="#financial-rail" class="ops-financial-payment-strip__more">
        Full ledger &amp; closeout
    </a>
</div>
