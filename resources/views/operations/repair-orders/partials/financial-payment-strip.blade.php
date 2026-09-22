@php
    $settlementBalanceDueCents = (int) ($financial['settlementBalanceDueCents'] ?? $financial['balanceDueCents'] ?? 0);
    $hasIssuedInvoice = (bool) ($financial['hasIssuedInvoice'] ?? false);
    $isPaid = (bool) ($financial['isPaid'] ?? ($hasIssuedInvoice && $settlementBalanceDueCents === 0));
    $hasSettlementDue = $hasIssuedInvoice && $settlementBalanceDueCents > 0;
    $tone = match (true) {
        $hasSettlementDue => 'ops-financial-payment-strip--due',
        $hasIssuedInvoice && $isPaid => 'ops-financial-payment-strip--paid',
        ($financial['unappliedDepositsCents'] ?? 0) > 0 => 'ops-financial-payment-strip--deposit',
        default => '',
    };
    $canTakePaymentCapture = (bool) ($financial['canTakePaymentCapture'] ?? false);
    $canRecordExternal = (bool) (($financial['canRecordPayment'] ?? false) || ($financial['canRecordManualDeposit'] ?? false));
    $openCapture = collect($financial['paymentCaptureAttempts'] ?? [])->first(
        fn (array $attempt): bool => (bool) ($attempt['isOpen'] ?? false),
    );
@endphp

<div id="financial-payment-panel" class="ops-financial-payment-strip {{ $tone }}">
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

        @if ($canTakePaymentCapture || $canRecordExternal)
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @if ($canTakePaymentCapture)
                    <button
                        type="button"
                        class="inline-flex min-h-10 w-full items-center justify-center rounded-sm bg-sky-700 px-3 text-xs font-bold text-white hover:bg-sky-800"
                        :class="paymentAction === 'capture' ? 'bg-sky-900' : ''"
                        @click="togglePayment('capture')"
                    >
                        Take payment
                    </button>
                @endif
                @if ($canRecordExternal)
                    <button
                        type="button"
                        class="inline-flex min-h-10 w-full items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 hover:border-slate-400"
                        :class="paymentAction === 'external' ? 'border-slate-500 bg-slate-50' : ''"
                        @click="togglePayment('external')"
                    >
                        Record external
                    </button>
                @endif
            </div>
        @endif

        @if ($canTakePaymentCapture)
            <div x-show="paymentAction === 'capture'" x-cloak>
                @include('operations.repair-orders.partials.financial-take-payment', [
                    'repairOrder' => $repairOrder,
                    'financial' => $financial,
                    'estimateVersion' => $estimateVersion,
                ])
            </div>
        @elseif (is_array($openCapture))
            @include('operations.repair-orders.partials.financial-take-payment', [
                'repairOrder' => $repairOrder,
                'financial' => $financial,
                'estimateVersion' => $estimateVersion,
            ])
        @endif

        @if ($financial['canRecordPayment'] ?? false)
            <div x-show="paymentAction === 'external'" x-cloak>
                @include('operations.repair-orders.partials.financial-record-payment', [
                    'repairOrder' => $repairOrder,
                    'financial' => $financial,
                    'estimateVersion' => $estimateVersion,
                ])
            </div>
        @endif

        @if ($financial['canRecordDeposit'] ?? false)
            @if ($financial['canRecordManualDeposit'] ?? false)
                <div x-show="paymentAction === 'external'" x-cloak>
                    @include('operations.repair-orders.partials.financial-record-deposit', [
                        'repairOrder' => $repairOrder,
                        'financial' => $financial,
                        'estimateVersion' => $estimateVersion,
                    ])
                </div>
            @elseif ($financial['suggestedDepositSatisfied'] ?? false)
                <div class="border border-emerald-200 bg-emerald-50/70 p-3 text-xs leading-5 text-emerald-950">
                    <p class="font-bold">Deposit on file</p>
                    <p class="mt-1">Remaining balance is already covered. Void a payment history entry if one was recorded in error.</p>
                </div>
            @endif
        @endif
    @endcan

    <button
        type="button"
        class="inline-flex min-h-8 w-full items-center justify-start text-[11px] font-bold text-slate-600 hover:text-slate-950 hover:underline"
        @click="moreOpen = ! moreOpen; if (moreOpen) { $nextTick(() => document.getElementById('financial-rail')?.scrollIntoView({ block: 'nearest' })); }"
    >
        More financial actions
    </button>
</div>
