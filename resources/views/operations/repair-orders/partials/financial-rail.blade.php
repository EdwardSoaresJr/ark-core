@php
    $tone = match ($financial['workflowPosture']) {
        'paid_ready_to_close', 'closed' => 'border-emerald-300',
        'partially_paid', 'invoice_issued' => 'border-amber-300',
        default => 'border-slate-300',
    };
@endphp

<div id="financial-rail" class="ops-review-panel {{ $tone }} scroll-mt-6 border-x-0 border-b-0">
    <div class="ops-review-panel-header">
        <p class="ops-eyebrow">Closeout</p>
    </div>

    <div class="ops-financial-rail-primary grid gap-2 p-3 text-sm">
        <div class="min-w-0">
            <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Payment status</p>
            <p class="mt-0.5 font-black text-slate-950">{{ $financial['workflowLabel'] }}</p>
            <p class="mt-1 text-xs font-semibold leading-4 text-slate-600">{{ $financial['workflowHint'] }}</p>
        </div>

        @if (($financial['invoiceNeedsRefresh'] ?? false) && ($financial['hasIssuedInvoice'] ?? false))
            <div class="rounded-sm border border-amber-300 bg-amber-50 px-3 py-2.5 text-xs leading-4 text-amber-950" role="status">
                <p class="font-bold">
                    @if ($financial['invoiceCustomerPresented'] ?? false)
                        Approved work changed. The customer invoice is now out of date.
                    @else
                        Approved work changed. Invoice total no longer matches approved work.
                    @endif
                </p>
                <p class="mt-1 font-semibold text-amber-900/80">
                    Invoice {{ $financial['invoiceTotal'] }}
                    · Approved {{ $financial['approvedWorkTotal'] }}
                    @if ($financial['invoiceDrift'] ?? null)
                        · Difference {{ ($financial['invoiceDriftCents'] ?? 0) > 0 ? '+' : '−' }}{{ $financial['invoiceDrift'] }}
                    @endif
                </p>
                <details class="mt-2">
                    <summary class="cursor-pointer font-bold text-amber-950 underline decoration-amber-400 underline-offset-2">View changes</summary>
                    <ul class="mt-1.5 list-disc space-y-0.5 pl-4 font-semibold text-amber-900/80">
                        <li>Invoice snapshot stays what was billed until you refresh.</li>
                        <li>Deposits and payments already collected stay applied to the new balance.</li>
                        @if (($financial['invoiceRevisionCount'] ?? 0) > 0)
                            <li>{{ $financial['invoiceRevisionCount'] }} prior invoice revision{{ $financial['invoiceRevisionCount'] === 1 ? '' : 's' }} retained for audit.</li>
                        @endif
                    </ul>
                </details>
                @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
                    @if ($financial['canRefreshInvoice'] ?? false)
                        <form
                            method="POST"
                            action="{{ route('operations.repair-orders.invoice.refresh', $repairOrder) }}"
                            class="mt-2"
                            data-refresh-scope="rail"
                            @submit.prevent="submitWorksheetForm($event)"
                        >
                            @csrf
                            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                            <button type="submit" class="inline-flex min-h-9 w-full items-center justify-center rounded-sm bg-amber-700 px-3 text-xs font-bold text-white hover:bg-amber-800">
                                Refresh Invoice
                            </button>
                        </form>
                    @elseif ($financial['invoiceRefreshBlockedBySettlement'] ?? false)
                        <p class="mt-2 font-semibold text-amber-900/80">
                            Write-off, refund, or store credit is on file — invoice cannot auto-update. Use credit memo, adjustment, or refund workflows.
                        </p>
                    @endif
                @endcan
            </div>
        @endif

        @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
            @if ($financial['canPost'])
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.post', $repairOrder) }}"
                    data-refresh-scope="rail"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-sm bg-emerald-700 px-3 text-xs font-bold text-white hover:bg-emerald-800">
                        Post to sales
                    </button>
                </form>
            @endif
        @endcan
    </div>

    <details class="ops-financial-rail-closeout border-t border-slate-200" open>
        <summary class="cursor-pointer select-none px-3 py-2 text-xs font-bold uppercase tracking-[0.08em] text-slate-600 hover:bg-slate-50">
            Ledger &amp; documents
        </summary>
        <div class="grid gap-2 border-t border-slate-100 p-3 text-sm">
            <dl class="divide-y divide-slate-100 rounded-sm border border-slate-200 bg-white text-sm">
                <div class="flex items-center justify-between gap-3 px-3 py-2">
                    <dt class="text-slate-500">Estimate total</dt>
                    <dd class="font-semibold tabular-nums text-slate-950">{{ $financial['estimateTotal'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-3 py-2">
                    <dt class="text-slate-500">Invoice</dt>
                    <dd class="font-semibold text-slate-950">{{ $financial['invoiceStatusLabel'] }}</dd>
                </div>
                @if ($financial['hasIssuedInvoice'])
                    @if (($financial['waivedCents'] ?? 0) > 0 || ($financial['excludesFromPostedSales'] ?? false))
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Would have cost</dt>
                            <dd class="font-black tabular-nums text-slate-950">{{ $financial['wouldHaveCost'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Collected</dt>
                            <dd class="font-semibold tabular-nums text-slate-800">{{ $financial['collected'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Waived · {{ $financial['collectionDispositionLabel'] }}</dt>
                            <dd class="font-semibold tabular-nums text-slate-800">{{ $financial['waived'] }}</dd>
                        </div>
                        @if ($financial['collectionDispositionReason'] ?? null)
                            <div class="px-3 py-2 text-xs font-semibold leading-4 text-slate-600">
                                {{ $financial['collectionDispositionReason'] }}
                            </div>
                        @endif
                    @else
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Invoice total</dt>
                            <dd class="font-black tabular-nums text-slate-950">{{ $financial['invoiceTotal'] }}</dd>
                        </div>
                    @endif
                    @if ($financial['depositsApplied'] !== '$0.00')
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Deposits</dt>
                            <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['depositsApplied'] }}</dd>
                        </div>
                    @endif
                    @if ($financial['paymentsApplied'] !== '$0.00')
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Payments</dt>
                            <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['paymentsApplied'] }}</dd>
                        </div>
                    @endif
                    @if (($financial['refundsApplied'] ?? '$0.00') !== '$0.00')
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Refunds</dt>
                            <dd class="font-semibold tabular-nums text-slate-800">+{{ $financial['refundsApplied'] }}</dd>
                        </div>
                    @endif
                    @if ($financial['creditsApplied'] !== '$0.00')
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Store credit applied</dt>
                            <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['creditsApplied'] }}</dd>
                        </div>
                    @endif
                    @if (($financial['writeOffs'] ?? '$0.00') !== '$0.00' && ! (($financial['waivedCents'] ?? 0) > 0 || ($financial['excludesFromPostedSales'] ?? false)))
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Write-offs</dt>
                            <dd class="font-semibold tabular-nums text-slate-800">−{{ $financial['writeOffs'] }}</dd>
                        </div>
                    @endif
                    @if ($financial['adjustments'] !== '$0.00')
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Adjustments</dt>
                            <dd class="font-semibold tabular-nums text-slate-800">{{ $financial['adjustments'] }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3 bg-slate-50 px-3 py-2">
                        <dt class="font-bold text-slate-700">Settlement balance</dt>
                        <dd class="font-black tabular-nums text-slate-950">{{ $financial['settlementBalanceDue'] ?? $financial['balanceDue'] }}</dd>
                    </div>
                    @if ($financial['oweTodayDiffersFromSettlement'] ?? false)
                        <div class="flex items-center justify-between gap-3 px-3 py-2">
                            <dt class="text-slate-500">Owe today</dt>
                            <dd class="font-semibold tabular-nums text-slate-800">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</dd>
                        </div>
                    @endif
                @elseif ($financial['unappliedDeposits'] !== '$0.00')
                    <div class="flex items-center justify-between gap-3 px-3 py-2">
                        <dt class="text-slate-500">Deposits on file</dt>
                        <dd class="font-semibold tabular-nums text-slate-800">{{ $financial['unappliedDeposits'] }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3 bg-slate-50 px-3 py-2">
                        <dt class="font-bold text-slate-700">Owe today</dt>
                        <dd class="font-black tabular-nums text-slate-950">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</dd>
                    </div>
                @else
                    <div class="flex items-center justify-between gap-3 bg-slate-50 px-3 py-2">
                        <dt class="font-bold text-slate-700">Owe today</dt>
                        <dd class="font-black tabular-nums text-slate-950">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</dd>
                    </div>
                @endif
            </dl>

            @if ($financial['storeCreditBalanceCents'] > 0)
                <p class="text-xs font-semibold leading-4 text-slate-600">
                    Customer store credit on file: {{ $financial['storeCreditBalance'] }}.
                </p>
            @endif

            @if ($financial['hasStoreCreditIssuance'])
                <p class="text-xs font-semibold leading-4 text-emerald-800">
                    Overpayment was issued to customer store credit. Invoice balance remains at zero.
                </p>
            @endif

            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
                @unless ($financial['financialRailReadOnly'] ?? false)
                    @include('operations.repair-orders.partials.invoice-email-form', [
                        'repairOrder' => $repairOrder,
                        'financial' => $financial,
                        'estimateVersion' => $estimateVersion,
                    ])

                    @if ($financial['canWaiveBalance'] ?? false)
                        <form
                            id="waive-balance"
                            method="POST"
                            action="{{ route('operations.repair-orders.waive-balance.store', $repairOrder) }}"
                            data-refresh-scope="rail"
                            data-continuity-focus="#waive-reason-{{ $repairOrder->repair_order_id }}"
                            @submit.prevent="submitWorksheetForm($event)"
                            class="grid gap-2 border border-amber-200 bg-amber-50/50 p-3 scroll-mt-6"
                        >
                            @csrf
                            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-amber-900">Waive balance</p>
                            <p class="text-[11px] font-semibold leading-4 text-slate-600">
                                Keeps the invoice total so the shop can see what this would have cost. Does not count as a payment.
                            </p>
                            <label class="sr-only" for="waive-disposition-{{ $repairOrder->repair_order_id }}">Disposition</label>
                            <select
                                id="waive-disposition-{{ $repairOrder->repair_order_id }}"
                                name="disposition"
                                required
                                class="h-9 rounded-sm border-slate-300 text-sm font-semibold text-slate-950"
                            >
                                <option value="">Choose…</option>
                                @foreach ($financial['waiveDispositionOptions'] ?? [] as $option)
                                    <option value="{{ $option['value'] }}" @selected(old('disposition') === $option['value'])>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <label class="sr-only" for="waive-reason-{{ $repairOrder->repair_order_id }}">Reason</label>
                            <textarea
                                id="waive-reason-{{ $repairOrder->repair_order_id }}"
                                name="reason"
                                required
                                rows="2"
                                maxlength="500"
                                placeholder="Why is this balance waived?"
                                class="rounded-sm border-slate-300 text-sm text-slate-700"
                            >{{ old('reason') }}</textarea>
                            <p class="text-[11px] font-semibold leading-4 text-slate-500">
                                Confirms waiving remaining settlement {{ $financial['settlementBalanceDue'] ?? $financial['balanceDue'] }}.
                            </p>
                            @error('disposition')
                                <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
                            @enderror
                            @error('reason')
                                <p class="text-xs font-semibold text-red-700">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-sm border border-amber-400 bg-white px-3 text-xs font-bold text-amber-950 hover:border-amber-500">
                                Waive remaining balance
                            </button>
                        </form>
                    @endif

                    @if ($financial['canRecordRefund'] ?? false)
                        <form
                            method="POST"
                            action="{{ route('operations.repair-orders.refund.store', $repairOrder) }}"
                            data-refresh-scope="rail"
                            data-continuity-focus="#refund-amount-{{ $repairOrder->repair_order_id }}"
                            @submit.prevent="submitWorksheetForm($event)"
                            class="grid gap-2 border border-slate-200 bg-white p-3"
                        >
                            @csrf
                            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Record Refund</p>
                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_8rem]">
                                <label class="sr-only" for="refund-amount-{{ $repairOrder->repair_order_id }}">Refund amount</label>
                                <input
                                    id="refund-amount-{{ $repairOrder->repair_order_id }}"
                                    name="amount"
                                    type="text"
                                    inputmode="decimal"
                                    required
                                    class="h-9 rounded-sm border-slate-300 text-sm font-semibold tabular-nums text-slate-950"
                                    placeholder="0.00"
                                >
                                <p class="self-center text-[11px] font-semibold leading-4 text-slate-500">Increases balance due</p>
                            </div>
                            <label class="sr-only" for="refund-reference-{{ $repairOrder->repair_order_id }}">Reference</label>
                            <input
                                id="refund-reference-{{ $repairOrder->repair_order_id }}"
                                name="reference"
                                type="text"
                                value="{{ old('reference') }}"
                                placeholder="Reference / note (optional)"
                                class="h-9 rounded-sm border-slate-300 text-sm text-slate-700"
                            >
                            <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-800 hover:border-slate-400">
                                Record Refund
                            </button>
                        </form>
                    @endif
                @endunless
            @endcan

            @if ($financial['invoice'])
                <a
                    href="{{ route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $financial['invoice']]) }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex min-h-9 w-full items-center justify-center rounded-sm border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:border-slate-400"
                >
                    Final Invoice PDF
                </a>
            @endif

            @if ($financial['closeoutBlockingReason'] && $repairOrder->status === App\Ark\Operations\RepairOrders\RepairOrderStatus::ReadyPickup)
                <p class="text-xs font-semibold leading-4 text-amber-900">{{ $financial['closeoutBlockingReason'] }}</p>
            @elseif ($financial['canClose'])
                <p class="text-xs font-semibold leading-4 text-emerald-800">Eligible to close after customer handoff.</p>
            @endif

            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
                @if ($financial['canPost'])
                    <p class="text-[11px] font-semibold leading-4 text-slate-500">
                        @if ($financial['excludesFromPostedSales'] ?? false)
                            Closing posts the job for history. {{ $financial['collectionDispositionLabel'] }} work is not counted in Sales Posted.
                        @else
                            Posts this job into sales reporting. Closing as Paid posts automatically — use this when the job is sold but not closed yet.
                        @endif
                    </p>
                @elseif ($financial['isPosted'])
                    <p class="text-xs font-semibold leading-4 {{ ($financial['excludesFromPostedSales'] ?? false) ? 'text-slate-700' : 'text-emerald-800' }}">
                        Posted {{ $financial['postedAtLabel'] }}
                        @if ($financial['excludesFromPostedSales'] ?? false)
                            · {{ $financial['collectionDispositionLabel'] }} — not counted in Sales Posted.
                        @else
                            · included in Sales Posted.
                        @endif
                    </p>
                @elseif (($financial['postBlockingReason'] ?? null) && $financial['hasIssuedInvoice'] && ($financial['isPaid'] ?? (($financial['settlementBalanceDueCents'] ?? 1) === 0)))
                    <p class="text-xs font-semibold leading-4 text-slate-500">{{ $financial['postBlockingReason'] }}</p>
                @endif
            @endcan

            @if ($financial['ledgerEntries']->isNotEmpty())
                <div class="border-t border-slate-200 pt-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Payment History</p>
                    <div class="mt-2 divide-y divide-slate-100">
                        @foreach ($financial['ledgerEntries'] as $entry)
                            <div class="py-2 text-xs leading-4 {{ $entry['isVoided'] ? 'opacity-50' : '' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-950">
                                            {{ $entry['typeLabel'] }}
                                            @if ($entry['method'])
                                                · {{ $entry['method'] }}
                                            @endif
                                        </p>
                                        <p class="mt-0.5 text-slate-500">
                                            {{ $entry['recordedAt'] ?? 'Pending' }}
                                            @if ($entry['recordedBy'])
                                                · {{ $entry['recordedBy'] }}
                                            @endif
                                        </p>
                                        @if ($entry['reference'])
                                            <p class="mt-0.5 text-slate-600">{{ $entry['reference'] }}</p>
                                        @endif
                                        @if ($entry['isVoided'])
                                            <p class="mt-0.5 font-semibold text-red-700">Voided</p>
                                        @endif
                                    </div>
                                    <p class="shrink-0 font-black tabular-nums text-slate-950">{{ $entry['amount'] }}</p>
                                </div>
                                @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
                                    @if (($financial['canManageLedgerEntries'] ?? false) && ! $entry['isVoided'] && in_array($entry['type'], ['deposit', 'payment', 'refund', 'store_credit'], true))
                                        <form
                                            method="POST"
                                            action="{{ route('operations.repair-orders.ledger-entries.destroy', [$repairOrder, $entry['id']]) }}"
                                            data-refresh-scope="rail"
                                            class="mt-2"
                                            @submit.prevent="if (confirm('Void this ledger entry? Balance due will be recalculated.')) { submitWorksheetForm($event); }"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex min-h-8 items-center justify-center rounded-sm border border-red-200 bg-white px-2.5 text-[11px] font-semibold text-red-800 hover:border-red-300">
                                                Void entry
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </details>
</div>
