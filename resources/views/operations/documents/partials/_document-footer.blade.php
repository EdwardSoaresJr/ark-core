@php
    $footer = $snapshot['document_footer'] ?? [];
    $approval = $footer['approval'] ?? [];
    $isPdf = ($variant ?? 'browser') === 'pdf';
    $documentType = $snapshot['document_type'] ?? 'estimate';
    $isInvoice = $documentType === 'invoice';
    $financial = $snapshot['financial'] ?? null;
    $summaryLabel = $isInvoice ? 'Invoice Summary' : 'Estimate Summary';
    $footerTotalLabel = $footer['total_label'] ?? 'Total';
    $standingDiscountCents = (int) ($snapshot['totals']['standing_discount_cents'] ?? 0);
    $standingDiscountLabel = $snapshot['totals']['standing_discount_label']
        ?? \App\Ark\Operations\Financial\StandingDiscountPresentation::label(
            data_get($snapshot, 'customer.customer_type'),
            $standingDiscountCents,
        )
        ?? 'Discount';
    $customerTotalsBreakdown = null;

    if ($isPdf) {
        $customerTotalsBreakdown = \App\Ark\Operations\Financial\CustomerEstimateTotalsPresentation::fromSnapshotTotals(
            $snapshot['totals'] ?? [],
            data_get($snapshot, 'customer.customer_type'),
        );
        $customerTotalsBreakdown['total_label'] = $footerTotalLabel;
        $customerTotalsBreakdown['is_invoice'] = $isInvoice && is_array($financial);
    }

    $authorizationText = trim(collect($footer['authorization'] ?? [])->filter()->implode(' '));
    $pdfImportantInformation = $isPdf
        ? app(\App\Ark\Operations\Documents\DocumentFooterPresenter::class)->pdfImportantInformationBullets($footer['important_information'] ?? [])
        : ($footer['important_information'] ?? []);
    $approvalForecast = is_array($snapshot['approval_forecast'] ?? null)
        ? $snapshot['approval_forecast']
        : null;
    $showApprovalForecast = (bool) ($approvalForecast['visible'] ?? false);

    if ($isPdf && $showApprovalForecast && is_array($customerTotalsBreakdown)) {
        // Forecast owns the destination total; breakdown quietly explains approved work.
        $customerTotalsBreakdown['total_label'] = 'Approved';
        $customerTotalsBreakdown['final_emphasis'] = false;
    }
@endphp

@if ($isPdf)
    <footer class="document-footer">
        <section class="footer-decision-area">
            <p class="footer-decision-heading">{{ $summaryLabel }}</p>
            <div class="footer-decision-body">
                <div class="footer-decision-main">
                    @if ($authorizationText !== '')
                        <p class="closing-authorization">{{ $authorizationText }}</p>
                    @endif

                    <div class="closing-approval">
                        <div class="closing-status-row">
                            <span class="closing-status-label">Approval Status</span>
                            <span class="closing-status-value">{{ $approval['status_label'] ?? 'Pending Approval' }}</span>
                        </div>

                        @if (! empty($approval['show_signature_lines']))
                            <div class="closing-signature-row">
                                <div class="closing-signature-field">
                                    <span class="closing-signature-label">Approved By</span>
                                    <span class="closing-signature-line" aria-hidden="true"></span>
                                </div>
                                <div class="closing-signature-field closing-signature-field--date">
                                    <span class="closing-signature-label">Date</span>
                                    <span class="closing-signature-line" aria-hidden="true"></span>
                                </div>
                            </div>
                        @elseif (! empty($approval['approved_by']) || ! empty($approval['approved_at_display']))
                            <div class="closing-evidence-row">
                                @if (! empty($approval['approved_by']))
                                    <span><strong>By:</strong> {{ $approval['approved_by'] }}</span>
                                @endif
                                @if (! empty($approval['approved_at_display']))
                                    <span><strong>Date:</strong> {{ $approval['approved_at_display'] }}</span>
                                @endif
                                @if (! empty($approval['source_label']))
                                    <span><strong>Via:</strong> {{ $approval['source_label'] }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="footer-decision-totals">
                    <div class="totals {{ $showApprovalForecast ? 'totals--with-forecast' : '' }}">
                        @include('operations.repair-orders.partials.repair-order-approval-forecast', [
                            'approvalForecast' => $approvalForecast,
                            'variant' => 'pdf',
                        ])

                        @if ($showApprovalForecast)
                            <p class="totals-breakdown-heading">Approved Work Breakdown</p>
                        @endif

                        <x-operations.estimate-totals-breakdown :breakdown="$customerTotalsBreakdown" variant="pdf" />

                        @if ($isInvoice && is_array($financial))
                            @if (($financial['deposits_applied_cents'] ?? 0) > 0)
                                <div class="totals-row totals-row--credit"><span>Deposits</span><strong>−{{ $financial['deposits_applied'] }}</strong></div>
                            @endif
                            @if (($financial['payments_applied_cents'] ?? 0) > 0)
                                <div class="totals-row totals-row--credit"><span>Payments</span><strong>−{{ $financial['payments_applied'] }}</strong></div>
                            @endif
                            @if (($financial['credits_applied_cents'] ?? 0) > 0)
                                <div class="totals-row totals-row--credit"><span>Store credit</span><strong>−{{ $financial['credits_applied'] }}</strong></div>
                            @endif
                            @if (($financial['write_offs_cents'] ?? 0) > 0)
                                <div class="totals-row totals-row--credit"><span>Write-offs</span><strong>−{{ $financial['write_offs'] }}</strong></div>
                            @endif
                            @if (! empty($financial['collection_waiver_label']))
                                <p class="footer-waiver-note">{{ $financial['collection_waiver_label'] }}</p>
                            @endif
                            @if (($financial['adjustments_cents'] ?? 0) !== 0)
                                <div class="totals-row"><span>Adjustments</span><strong>{{ $financial['adjustments'] }}</strong></div>
                            @endif
                            <div class="totals-row final"><span>Balance due</span><span>{{ $financial['balance_due'] }}</span></div>
                        @endif
                    </div>

                    @if ($isInvoice && is_array($financial) && ! empty($financial['entries']))
                        <div class="footer-payments">
                            <p class="footer-payments-heading">Payments</p>
                            <ul class="footer-payments-list">
                                @foreach ($financial['entries'] as $entry)
                                    <li>
                                        <span class="footer-payment-label">
                                            {{ $entry['recorded_at_display'] ?? '' }}
                                            @if (! empty($entry['method_label']))
                                                · {{ $entry['method_label'] }}
                                            @endif
                                            · {{ $entry['type_label'] ?? 'Payment' }}
                                        </span>
                                        <span class="footer-payment-amount">
                                            @if ($entry['reduces_balance'] ?? false)
                                                −{{ $entry['amount'] }}
                                            @else
                                                {{ $entry['amount'] }}
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            @if (! empty($pdfImportantInformation) || ! empty($footer['customer_type_terms']['bullets'] ?? []))
                <section class="footer-notes footer-notes--in-summary">
                    @if (! empty($pdfImportantInformation))
                        <p class="footer-eyebrow">Important Information</p>
                        <ul class="footer-bullets-compact footer-bullets-compact--pdf">
                            @foreach ($pdfImportantInformation as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($footer['customer_type_terms']['bullets'] ?? []))
                        <p class="footer-eyebrow footer-eyebrow--terms">{{ $footer['customer_type_terms']['heading'] }}</p>
                        <ul class="footer-bullets-compact footer-bullets-compact--single footer-bullets-compact--pdf">
                            @foreach ($footer['customer_type_terms']['bullets'] as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @php $repairPortal = $snapshot['repair_portal'] ?? null; @endphp
            @if (is_array($repairPortal) && filled($repairPortal['qr_data_uri'] ?? null))
                <section class="repair-portal-ad" style="margin-top:0.14in;padding-top:0.1in;border-top:1px solid #cbd5e1;display:flex;gap:0.14in;align-items:center;">
                    <div style="flex:1;min-width:0;">
                        <p style="margin:0;font-size:9px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#64748b;">{{ $repairPortal['headline'] ?? 'Vehicle Portal' }}</p>
                        <p style="margin:4px 0 0;font-size:12px;font-weight:700;color:#0f172a;">{{ $repairPortal['cta'] ?? 'View your vehicle online' }}</p>
                        <p style="margin:4px 0 0;font-size:10px;color:#334155;">Scan to view</p>
                        <ul style="margin:6px 0 0;padding-left:1.1em;font-size:10px;color:#334155;">
                            @foreach (($repairPortal['bullets'] ?? []) as $bullet)
                                <li style="margin:0 0 2px;">{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div style="flex-shrink:0;text-align:center;">
                        <img src="{{ $repairPortal['qr_data_uri'] }}" alt="Vehicle portal QR" width="88" height="88" style="display:block;width:88px;height:88px;">
                    </div>
                </section>
            @endif
        </section>
    </footer>
@else
    <footer class="mt-4 border-t border-slate-300 pt-4">
        <div class="flex justify-end">
            <div class="w-full max-w-sm">
                <div class="mb-2 text-right">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $summaryLabel }}</p>
                </div>
                <div class="grid gap-2 border border-slate-300 bg-white p-3 text-sm">
                    <div class="ops-total-row"><span>Labor</span><strong>{{ $snapshot['totals']['labor'] }}</strong></div>
                    <div class="ops-total-row"><span>Parts</span><strong>{{ $snapshot['totals']['parts'] }}</strong></div>
                    <div class="ops-total-row"><span>Fees</span><strong>{{ $snapshot['totals']['fees'] }}</strong></div>
                    @if ($standingDiscountCents > 0)
                        <div class="ops-total-row"><span>{{ $standingDiscountLabel }}</span><strong class="text-emerald-700">−{{ $snapshot['totals']['standing_discount'] }}</strong></div>
                    @endif
                    <div class="ops-total-row"><span>Tax</span><strong>{{ $snapshot['totals']['tax'] }}</strong></div>
                    <div class="ops-total-row ops-total-row--final"><span>{{ $footerTotalLabel }}</span><span>{{ $snapshot['totals']['total'] }}</span></div>
                </div>
            </div>
        </div>

        @if ($authorizationText !== '' || ! empty($approval['status_label']))
            <section class="mt-4 border border-slate-400 bg-slate-50 p-4">
                @if ($authorizationText !== '')
                    <p class="text-sm font-semibold leading-6 text-slate-950">{{ $authorizationText }}</p>
                @endif

                <div class="{{ $authorizationText !== '' ? 'mt-4 border-t border-slate-300 pt-3' : '' }}">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Approval Status</p>
                        <p class="text-base font-black text-slate-950">{{ $approval['status_label'] ?? 'Pending Approval' }}</p>
                    </div>

                    @if (! empty($approval['show_signature_lines']))
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Approved By</p>
                                <p class="mt-5 border-b border-slate-500">&nbsp;</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date</p>
                                <p class="mt-5 border-b border-slate-500">&nbsp;</p>
                            </div>
                        </div>
                    @elseif (! empty($approval['approved_by']) || ! empty($approval['approved_at_display']))
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-700">
                            @if (! empty($approval['approved_by']))
                                <span><span class="font-semibold text-slate-950">By:</span> {{ $approval['approved_by'] }}</span>
                            @endif
                            @if (! empty($approval['approved_at_display']))
                                <span><span class="font-semibold text-slate-950">Date:</span> {{ $approval['approved_at_display'] }}</span>
                            @endif
                            @if (! empty($approval['source_label']))
                                <span><span class="font-semibold text-slate-950">Via:</span> {{ $approval['source_label'] }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if (! empty($footer['important_information']))
            <section class="mt-4 border-t border-slate-200 pt-3">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Important Information</p>
                <ul class="mt-2 columns-1 gap-4 text-sm leading-5 text-slate-700 sm:columns-2">
                    @foreach ($footer['important_information'] as $bullet)
                        <li class="mb-1.5 break-inside-avoid">{{ $bullet }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($footer['customer_type_terms']['bullets'] ?? []))
            <section class="mt-3 border-t border-slate-200 pt-3">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $footer['customer_type_terms']['heading'] }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-5 text-slate-700">
                    @foreach ($footer['customer_type_terms']['bullets'] as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </footer>
@endif
