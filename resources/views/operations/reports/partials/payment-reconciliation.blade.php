@php
    $reconciliation = $paymentReconciliation;
    $postedSummary = $reconciliation['posted_ro_summary'];
@endphp

<section class="card mb-3" data-report-section="posted-ro-summary">
    <div class="card-header">
        <div>
            <h3 class="card-title">Posted RO Summary</h3>
            <div class="card-subtitle">ROs posted in this range.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <tbody>
                <tr>
                    <th scope="row" class="fw-bold">Posted invoice sales</th>
                    <td class="text-end fw-bold ark-money">{{ $postedSummary['service'] }}</td>
                </tr>
                <tr>
                    <th scope="row" class="fw-bold">Sales tax collected</th>
                    <td class="text-end fw-bold ark-money">{{ $postedSummary['tax'] }}</td>
                </tr>
                <tr class="table-active">
                    <th scope="row" class="fw-bold">Invoice total</th>
                    <td class="text-end fw-bold ark-money">{{ $postedSummary['total'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @if (count($postedSummary['details']) > 0)
        <details class="card-footer">
            <summary class="subheader">{{ count($postedSummary['details']) }} posted RO{{ count($postedSummary['details']) === 1 ? '' : 's' }}</summary>
            <div class="list-group mt-2">
                @foreach ($postedSummary['details'] as $detail)
                    <a href="{{ route('operations.repair-orders.show', $detail['repair_order_pk']) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div style="min-width: 0">
                                <div class="fw-bold text-truncate">#{{ $detail['repair_order_id'] }} · {{ $detail['customer'] }}</div>
                                <div class="small text-secondary text-truncate mt-1">{{ $detail['vehicle'] }} · posted {{ $detail['posted_at'] }}</div>
                            </div>
                            <div class="fw-bold ark-money text-nowrap">{{ $detail['amount'] }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </details>
    @endif
</section>

<section class="card mb-3" data-report-section="payments-reconciliation">
    <div class="card-header">
        <div>
            <h3 class="card-title">Payments Reconciliation</h3>
            <div class="card-subtitle">Cash collected beside the invoice total. Write-offs are not cash.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Line</th>
                    <th scope="col" class="text-end">Amount</th>
                    <th scope="col">Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reconciliation['rows'] as $row)
                    <tr @class(['table-active' => $row['emphasis']])>
                        <td class="fw-bold">{{ $row['label'] }}</td>
                        <td @class([
                            'text-end ark-money',
                            'fw-bold' => $row['emphasis'],
                            'fw-medium' => ! $row['emphasis'],
                            'text-warning' => ($row['tone'] ?? null) === 'subtract' && $row['amount'] !== '$0.00' && $row['amount'] !== '−$0.00',
                        ])>{{ $row['amount'] }}</td>
                        <td class="small text-secondary">{{ $row['note'] }}</td>
                    </tr>
                    @if (count($row['details']) > 0)
                        <tr>
                            <td colspan="3">
                                <details>
                                    <summary class="subheader">{{ count($row['details']) }} RO{{ count($row['details']) === 1 ? '' : 's' }}</summary>
                                    <div class="list-group mt-2">
                                        @foreach ($row['details'] as $detail)
                                            <a href="{{ route('operations.repair-orders.show', $detail['repair_order_pk']) }}" class="list-group-item list-group-item-action">
                                                <div class="d-flex align-items-start justify-content-between gap-3">
                                                    <div style="min-width: 0">
                                                        <div class="fw-bold text-truncate">#{{ $detail['repair_order_id'] }} · {{ $detail['customer'] }}</div>
                                                        <div class="small text-secondary text-truncate mt-1">
                                                            {{ $detail['vehicle'] }}
                                                            · paid {{ $detail['payment_at'] }}
                                                            @if ($detail['posted_at'])
                                                                · posted {{ $detail['posted_at'] }}
                                                            @else
                                                                · not posted
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="fw-bold ark-money text-nowrap">{{ $detail['amount'] }}</div>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    <div @class([
        'card-footer small',
        'bg-green-lt' => $reconciliation['reconciles'],
        'bg-yellow-lt' => ! $reconciliation['reconciles'],
    ]) data-report-reconciles="{{ $reconciliation['reconciles'] ? 'yes' : 'no' }}">
        @if ($reconciliation['reconciles'])
            Reconciled cash matches posted invoice totals for this range.
        @else
            Reconciliation gap: {{ $reconciliation['delta_label'] }} vs posted invoice total - expand lines above to find the RO.
        @endif
    </div>
</section>
