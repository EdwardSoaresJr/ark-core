<div id="financial-rail" class="scroll-mt-6">
    <div class="card-body d-flex flex-column gap-3">
        <div id="payment-posture" data-continuity-region="settlement" style="min-width: 0">
            <div class="subheader">Payment status</div>
            <div class="fw-bold">{{ $financial['workflowLabel'] }}</div>
            <div class="small text-secondary">{{ $financial['workflowHint'] }}</div>
        </div>

        <div id="invoice-posture" data-continuity-region="invoice-posture">
        @if (($financial['invoiceNeedsRefresh'] ?? false) && ($financial['hasIssuedInvoice'] ?? false))
            <div class="alert alert-warning mb-0 small" role="status">
                <div class="fw-bold">
                    @if ($financial['invoiceCustomerPresented'] ?? false)
                        Approved work changed. The customer invoice is now out of date.
                    @else
                        Approved work changed. Invoice total no longer matches approved work.
                    @endif
                </div>
                <div class="ark-money">
                    Invoice {{ $financial['invoiceTotal'] }}
                    · Approved {{ $financial['approvedWorkTotal'] }}
                    @if ($financial['invoiceDrift'] ?? null)
                        · Difference {{ ($financial['invoiceDriftCents'] ?? 0) > 0 ? '+' : '−' }}{{ $financial['invoiceDrift'] }}
                    @endif
                </div>
                <details class="mt-2">
                    <summary class="fw-bold">View changes</summary>
                    <ul class="mt-1 mb-0 ps-3">
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
                            data-refresh-scope="financial"
                            @submit.prevent="submitWorksheetForm($event)"
                        >
                            @csrf
                            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                            <button type="submit" class="btn btn-warning w-100">Refresh Invoice</button>
                        </form>
                    @elseif ($financial['invoiceRefreshBlockedBySettlement'] ?? false)
                        <div class="mt-2">
                            Write-off, refund, or store credit is on file - invoice cannot auto-update. Use credit memo, adjustment, or refund workflows.
                        </div>
                    @endif
                @endcan
            </div>
        @endif
        </div>

        @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
            <div id="closeout-eligibility" data-continuity-region="settlement">
                @if ($financial['canPost'])
                    <form
                        method="POST"
                        action="{{ route('operations.repair-orders.post', $repairOrder) }}"
                        data-refresh-scope="financial"
                        @submit.prevent="submitWorksheetForm($event)"
                    >
                        @csrf
                        <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                        <button type="submit" class="btn btn-success w-100">Post to sales</button>
                    </form>
                @endif
            </div>
        @endcan
    </div>

    <details class="border-top" open>
        <summary class="card-body py-2 subheader">Ledger &amp; documents</summary>
        <div class="card-body pt-0 d-flex flex-column gap-3">
            <dl class="mb-0 border rounded small">
                <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                    <dt class="fw-normal text-secondary">Estimate total</dt>
                    <dd class="mb-0 fw-medium ark-money">{{ $financial['estimateTotal'] }}</dd>
                </div>
                <div id="settlement-invoice" data-continuity-region="settlement" class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                    <dt class="fw-normal text-secondary">Invoice</dt>
                    <dd class="mb-0 fw-medium">{{ $financial['invoiceStatusLabel'] }}</dd>
                </div>
                @if ($financial['hasIssuedInvoice'])
                    @if (($financial['waivedCents'] ?? 0) > 0 || ($financial['excludesFromPostedSales'] ?? false))
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Would have cost</dt>
                            <dd class="mb-0 fw-bold ark-money">{{ $financial['wouldHaveCost'] }}</dd>
                        </div>
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Collected</dt>
                            <dd class="mb-0 ark-money">{{ $financial['collected'] }}</dd>
                        </div>
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Waived · {{ $financial['collectionDispositionLabel'] }}</dt>
                            <dd class="mb-0 ark-money">{{ $financial['waived'] }}</dd>
                        </div>
                        @if ($financial['collectionDispositionReason'] ?? null)
                            <div class="px-3 py-2 border-bottom text-secondary">
                                {{ $financial['collectionDispositionReason'] }}
                            </div>
                        @endif
                    @else
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Invoice total</dt>
                            <dd class="mb-0 fw-bold ark-money">{{ $financial['invoiceTotal'] }}</dd>
                        </div>
                    @endif
                    <div id="settlement-balance" data-continuity-region="settlement">
                    @if ($financial['depositsApplied'] !== '$0.00')
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Deposits</dt>
                            <dd class="mb-0 ark-money">−{{ $financial['depositsApplied'] }}</dd>
                        </div>
                    @endif
                    @if ($financial['paymentsApplied'] !== '$0.00')
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Payments</dt>
                            <dd class="mb-0 ark-money">−{{ $financial['paymentsApplied'] }}</dd>
                        </div>
                    @endif
                    @if (($financial['refundsApplied'] ?? '$0.00') !== '$0.00')
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Refunds</dt>
                            <dd class="mb-0 ark-money">+{{ $financial['refundsApplied'] }}</dd>
                        </div>
                    @endif
                    @if ($financial['creditsApplied'] !== '$0.00')
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Store credit applied</dt>
                            <dd class="mb-0 ark-money">−{{ $financial['creditsApplied'] }}</dd>
                        </div>
                    @endif
                    @if (($financial['writeOffs'] ?? '$0.00') !== '$0.00' && ! (($financial['waivedCents'] ?? 0) > 0 || ($financial['excludesFromPostedSales'] ?? false)))
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Write-offs</dt>
                            <dd class="mb-0 ark-money">−{{ $financial['writeOffs'] }}</dd>
                        </div>
                    @endif
                    @if ($financial['adjustments'] !== '$0.00')
                        <div class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                            <dt class="fw-normal text-secondary">Adjustments</dt>
                            <dd class="mb-0 ark-money">{{ $financial['adjustments'] }}</dd>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between gap-3 px-3 py-2 bg-secondary-lt">
                        <dt class="fw-bold">Settlement balance</dt>
                        <dd class="mb-0 fw-bold ark-money">{{ $financial['settlementBalanceDue'] ?? $financial['balanceDue'] }}</dd>
                    </div>
                    </div>
                    @if ($financial['oweTodayDiffersFromSettlement'] ?? false)
                        <div id="financial-position-closeout" data-continuity-region="financial-position" class="d-flex justify-content-between gap-3 px-3 py-2 border-top">
                            <dt class="fw-normal text-secondary">Owe today</dt>
                            <dd class="mb-0 ark-money">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</dd>
                        </div>
                    @endif
                @elseif ($financial['unappliedDeposits'] !== '$0.00')
                    <div id="settlement-balance" data-continuity-region="settlement" class="d-flex justify-content-between gap-3 px-3 py-2 border-bottom">
                        <dt class="fw-normal text-secondary">Deposits on file</dt>
                        <dd class="mb-0 ark-money">{{ $financial['unappliedDeposits'] }}</dd>
                    </div>
                    <div id="financial-position-closeout" data-continuity-region="financial-position" class="d-flex justify-content-between gap-3 px-3 py-2 bg-secondary-lt">
                        <dt class="fw-bold">Owe today</dt>
                        <dd class="mb-0 fw-bold ark-money">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</dd>
                    </div>
                @else
                    <div id="settlement-balance" data-continuity-region="settlement"></div>
                    <div id="financial-position-closeout" data-continuity-region="financial-position" class="d-flex justify-content-between gap-3 px-3 py-2 bg-secondary-lt">
                        <dt class="fw-bold">Owe today</dt>
                        <dd class="mb-0 fw-bold ark-money">{{ $financial['oweToday'] ?? $financial['projectedBalance'] }}</dd>
                    </div>
                @endif
            </dl>

            @if ($financial['storeCreditBalanceCents'] > 0)
                <div class="small text-secondary">
                    Customer store credit on file: {{ $financial['storeCreditBalance'] }}.
                </div>
            @endif

            @if ($financial['hasStoreCreditIssuance'])
                <div class="small text-green">
                    Overpayment was issued to customer store credit. Invoice balance remains at zero.
                </div>
            @endif

            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
                @include('operations.repair-orders.partials.invoice-email-form', [
                    'repairOrder' => $repairOrder,
                    'financial' => $financial,
                    'estimateVersion' => $estimateVersion,
                ])

                @unless ($financial['financialRailReadOnly'] ?? false)
                    @if ($financial['canWaiveBalance'] ?? false)
                        <form
                            id="waive-balance"
                            method="POST"
                            action="{{ route('operations.repair-orders.waive-balance.store', $repairOrder) }}"
                            data-refresh-scope="financial"
                            data-continuity-focus="#waive-reason-{{ $repairOrder->repair_order_id }}"
                            @submit.prevent="submitWorksheetForm($event)"
                            class="border border-warning rounded p-3 d-flex flex-column gap-2 scroll-mt-6"
                        >
                            @csrf
                            <div>
                                <div class="subheader text-warning">Waive balance</div>
                                <div class="small text-secondary">
                                    Keeps the invoice total so the shop can see what this would have cost. Does not count as a payment.
                                </div>
                            </div>
                            <label class="visually-hidden" for="waive-disposition-{{ $repairOrder->repair_order_id }}">Disposition</label>
                            <select
                                id="waive-disposition-{{ $repairOrder->repair_order_id }}"
                                name="disposition"
                                required
                                @class(['form-select', 'is-invalid' => $errors->has('disposition')])
                            >
                                <option value="">Choose…</option>
                                @foreach ($financial['waiveDispositionOptions'] ?? [] as $option)
                                    <option value="{{ $option['value'] }}" @selected(old('disposition') === $option['value'])>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <label class="visually-hidden" for="waive-reason-{{ $repairOrder->repair_order_id }}">Reason</label>
                            <textarea
                                id="waive-reason-{{ $repairOrder->repair_order_id }}"
                                name="reason"
                                required
                                rows="2"
                                maxlength="500"
                                placeholder="Why is this balance waived?"
                                @class(['form-control', 'is-invalid' => $errors->has('reason')])
                            >{{ old('reason') }}</textarea>
                            <div class="small text-secondary">
                                Confirms waiving remaining settlement {{ $financial['settlementBalanceDue'] ?? $financial['balanceDue'] }}.
                            </div>
                            @error('disposition')
                                <div class="small text-danger">{{ $message }}</div>
                            @enderror
                            @error('reason')
                                <div class="small text-danger">{{ $message }}</div>
                            @enderror
                            <button type="submit" class="btn btn-outline-warning w-100">Waive remaining balance</button>
                        </form>
                    @endif

                    @if ($financial['canRecordRefund'] ?? false)
                        <form
                            method="POST"
                            action="{{ route('operations.repair-orders.refund.store', $repairOrder) }}"
                            data-refresh-scope="financial"
                            data-continuity-focus="#refund-amount-{{ $repairOrder->repair_order_id }}"
                            @submit.prevent="submitWorksheetForm($event)"
                            class="border rounded p-3 d-flex flex-column gap-2"
                        >
                            @csrf
                            <div class="subheader">Record Refund</div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="visually-hidden" for="refund-amount-{{ $repairOrder->repair_order_id }}">Refund amount</label>
                                <input
                                    id="refund-amount-{{ $repairOrder->repair_order_id }}"
                                    name="amount"
                                    type="text"
                                    inputmode="decimal"
                                    required
                                    class="form-control ark-money"
                                    placeholder="0.00"
                                >
                                <span class="small text-secondary text-nowrap">Increases balance due</span>
                            </div>
                            <label class="visually-hidden" for="refund-reference-{{ $repairOrder->repair_order_id }}">Reference</label>
                            <input
                                id="refund-reference-{{ $repairOrder->repair_order_id }}"
                                name="reference"
                                type="text"
                                value="{{ old('reference') }}"
                                placeholder="Reference / note (optional)"
                                class="form-control"
                            >
                            <button type="submit" class="btn btn-outline-secondary w-100">Record Refund</button>
                        </form>
                    @endif
                @endunless
            @endcan

            @if ($financial['invoice'])
                <a
                    href="{{ route('operations.repair-orders.estimate-documents.pdf.show', [$repairOrder, $financial['invoice']]) }}"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-outline-secondary w-100"
                >
                    Final Invoice PDF
                </a>
            @endif

            @if ($financial['closeoutBlockingReason'] && $repairOrder->status === App\Ark\Operations\RepairOrders\RepairOrderStatus::ReadyPickup)
                <div class="small text-warning">{{ $financial['closeoutBlockingReason'] }}</div>
            @elseif ($financial['canClose'])
                <div class="small text-green">Eligible to close after customer handoff.</div>
            @endif

            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
                @if ($financial['canPost'])
                    <div class="small text-secondary">
                        @if ($financial['excludesFromPostedSales'] ?? false)
                            Closing posts the job for history. The invoice stays in posted invoice sales. The write-off is reported separately.
                        @else
                            Posts this job into sales reporting. Closing as Paid posts automatically - use this when the job is sold but not closed yet.
                        @endif
                    </div>
                @elseif ($financial['isPosted'])
                    <div @class(['small', 'text-secondary' => $financial['excludesFromPostedSales'] ?? false, 'text-green' => ! ($financial['excludesFromPostedSales'] ?? false)])>
                        Posted {{ $financial['postedAtLabel'] }}
                        @if ($financial['excludesFromPostedSales'] ?? false)
                            · {{ $financial['collectionDispositionLabel'] }} - invoice stays in posted invoice sales. Write-off is separate.
                        @else
                            · included in posted invoice sales.
                        @endif
                    </div>
                @elseif (($financial['postBlockingReason'] ?? null) && $financial['hasIssuedInvoice'] && ($financial['isPaid'] ?? (($financial['settlementBalanceDueCents'] ?? 1) === 0)))
                    <div class="small text-secondary">{{ $financial['postBlockingReason'] }}</div>
                @endif
            @endcan

            <div id="settlement-history-closeout" data-continuity-region="settlement" @class(['border-top pt-2' => $financial['ledgerEntries']->isNotEmpty()])>
            @if ($financial['ledgerEntries']->isNotEmpty())
                <div class="subheader">Payment History</div>
                <div class="list-group list-group-flush">
                    @foreach ($financial['ledgerEntries'] as $entry)
                        <div @class(['list-group-item px-0 py-2 small', 'opacity-50' => $entry['isVoided']])>
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div style="min-width: 0">
                                    <div class="fw-bold">
                                        {{ $entry['typeLabel'] }}
                                        @if ($entry['method'])
                                            · {{ $entry['method'] }}
                                        @endif
                                    </div>
                                    <div class="text-secondary">
                                        {{ $entry['recordedAt'] ?? 'Pending' }}
                                        @if ($entry['recordedBy'])
                                            · {{ $entry['recordedBy'] }}
                                        @endif
                                    </div>
                                    @if ($entry['reference'])
                                        <div class="text-secondary">{{ $entry['reference'] }}</div>
                                    @endif
                                    @if ($entry['isVoided'])
                                        <div class="fw-bold text-danger">Voided</div>
                                    @endif
                                </div>
                                <div class="fw-bold ark-money text-nowrap">{{ $entry['amount'] }}</div>
                            </div>
                            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersCloseout->value)
                                @if (($financial['canManageLedgerEntries'] ?? false) && ! $entry['isVoided'] && in_array($entry['type'], ['deposit', 'payment', 'refund', 'store_credit'], true))
                                    <form
                                        method="POST"
                                        action="{{ route('operations.repair-orders.ledger-entries.destroy', [$repairOrder, $entry['id']]) }}"
                                        data-refresh-scope="financial"
                                        class="mt-2"
                                        @submit.prevent="if (confirm('Void this ledger entry? Balance due will be recalculated.')) { submitWorksheetForm($event); }"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Void entry</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    @endforeach
                </div>
            @endif
            </div>
        </div>
    </details>
</div>
