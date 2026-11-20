<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Account Statement</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; color: #1f2937; font-size: 12px; margin: 0; }
        .page { padding: 36px 40px; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 24px 0 8px; }
        .muted { color: #6b7280; }
        .header { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 24px; }
        .totals { display: flex; gap: 24px; margin: 16px 0 8px; }
        .totals div { min-width: 120px; }
        .totals strong { display: block; font-size: 16px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
        .text-end { text-align: right; }
        .danger { color: #b91c1c; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
@php
    $blocks = ($combined ?? false) ? ($statements ?? []) : [($statement ?? [])];
@endphp
@foreach ($blocks as $statement)
    <section class="page">
        <div class="header">
            <div>
                <h1>{{ $statement['shop']['name'] }}</h1>
                <div class="muted">{{ $statement['shop']['address'] }}</div>
                <div class="muted">{{ $statement['shop']['phone'] }} {{ $statement['shop']['email'] }}</div>
            </div>
            <div class="text-end">
                <div style="font-size: 18px; font-weight: 700;">Account Statement</div>
                <div class="muted">As of {{ $statement['as_of']->timezone(config('app.timezone'))->format('M j, Y') }}</div>
            </div>
        </div>
        <div>
            <strong>{{ $statement['customer']['name'] }}</strong>
            @foreach ($statement['customer']['address_lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
            @if ($statement['customer']['email'])
                <div class="muted">{{ $statement['customer']['email'] }}</div>
            @endif
        </div>
        <div class="totals">
            <div><span class="muted">Balance due</span><strong>{{ $money($statement['total_outstanding_cents']) }}</strong></div>
            <div><span class="muted">Past due</span><strong class="{{ $statement['past_due_cents'] > 0 ? 'danger' : '' }}">{{ $money($statement['past_due_cents']) }}</strong></div>
            <div><span class="muted">Current</span><strong>{{ $money($statement['current_cents']) }}</strong></div>
        </div>
        <h2>Outstanding invoices</h2>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>RO / Vehicle</th>
                    <th>Issued</th>
                    <th>Due</th>
                    <th class="text-end">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statement['outstanding_invoices'] as $invoice)
                    <tr>
                        <td>INV #{{ $invoice['document_number'] }}</td>
                        <td>RO #{{ $invoice['repair_order_number'] }}<div class="muted">{{ $invoice['vehicle_label'] ?: '—' }}</div></td>
                        <td>{{ $invoice['issued_at']?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</td>
                        <td>{{ $invoice['due_at']?->timezone(config('app.timezone'))->format('M j, Y') ?? '—' }}</td>
                        <td class="text-end">{{ $money($invoice['balance_due_cents']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No outstanding invoices.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if (count($statement['payments']) > 0)
            <h2>Recent payments</h2>
            <table>
                <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    @foreach ($statement['payments'] as $payment)
                        <tr>
                            <td>{{ $payment['recorded_at']?->timezone(config('app.timezone'))->format('M j, Y') }}</td>
                            <td>{{ $payment['payment_method'] }}</td>
                            <td>{{ $payment['reference'] ?: '—' }}</td>
                            <td class="text-end">{{ $money($payment['amount_cents']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endforeach
</body>
</html>
