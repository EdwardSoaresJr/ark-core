@php
    $shop = data_get($snapshot, 'shop', []);
    $customer = data_get($snapshot, 'customer', []);
    $vehicle = data_get($snapshot, 'vehicle', []);
    $repairOrder = data_get($snapshot, 'repair_order', []);
    $concerns = data_get($snapshot, 'concerns', []);
    $totals = data_get($snapshot, 'totals', []);
    $title = $document->isInvoice() ? 'Invoice' : 'Estimate';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; }
        h1 { font-size: 18pt; margin: 0 0 8pt; }
        h2 { font-size: 12pt; margin: 16pt 0 6pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 6pt; }
        th, td { border: 1px solid #ccc; padding: 4pt 6pt; text-align: left; vertical-align: top; }
        th { background: #f3f3f3; }
        .meta td { border: none; padding: 2pt 0; }
        .right { text-align: right; }
        .muted { color: #555; }
    </style>
</head>
<body>
    <h1>{{ data_get($shop, 'name', config('app.name')) }} — {{ $title }}</h1>
    <table class="meta">
        <tr>
            <td>
                @if(data_get($shop, 'phone'))<div>{{ data_get($shop, 'phone') }}</div>@endif
                @if(data_get($shop, 'email'))<div>{{ data_get($shop, 'email') }}</div>@endif
                @if(data_get($shop, 'address'))<div>{!! nl2br(e((string) data_get($shop, 'address'))) !!}</div>@endif
            </td>
            <td class="right">
                <div><strong>RO</strong> {{ data_get($repairOrder, 'number', data_get($repairOrder, 'id', '—')) }}</div>
                <div><strong>Date</strong> {{ now()->toFormattedDateString() }}</div>
            </td>
        </tr>
    </table>

    <h2>Customer / vehicle</h2>
    <table class="meta">
        <tr>
            <td>
                <div>{{ data_get($customer, 'name', 'Customer') }}</div>
                @if(data_get($customer, 'phone'))<div class="muted">{{ data_get($customer, 'phone') }}</div>@endif
                @if(data_get($customer, 'email'))<div class="muted">{{ data_get($customer, 'email') }}</div>@endif
            </td>
            <td class="right">
                <div>
                    {{ trim(implode(' ', array_filter([
                        data_get($vehicle, 'year'),
                        data_get($vehicle, 'make'),
                        data_get($vehicle, 'model'),
                    ]))) ?: 'Vehicle' }}
                </div>
                @if(data_get($vehicle, 'vin'))<div class="muted">VIN {{ data_get($vehicle, 'vin') }}</div>@endif
                @if(data_get($vehicle, 'license_plate'))<div class="muted">Plate {{ data_get($vehicle, 'license_plate') }}</div>@endif
            </td>
        </tr>
    </table>

    <h2>Line items</h2>
    <table>
        <thead>
            <tr>
                <th>Concern / item</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($concerns as $concern)
                <tr>
                    <td colspan="2"><strong>{{ data_get($concern, 'title', data_get($concern, 'name', 'Concern')) }}</strong></td>
                </tr>
                @foreach ((array) data_get($concern, 'lines', data_get($concern, 'line_items', [])) as $line)
                    <tr>
                        <td>{{ data_get($line, 'description', data_get($line, 'label', 'Item')) }}</td>
                        <td class="right">{{ data_get($line, 'amount_formatted', data_get($line, 'total_formatted', data_get($line, 'total', ''))) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="2" class="muted">No line items in this document snapshot.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Totals</h2>
    <table>
        @foreach ([
            'Parts' => data_get($totals, 'parts_formatted', data_get($totals, 'parts')),
            'Labor' => data_get($totals, 'labor_formatted', data_get($totals, 'labor')),
            'Fees' => data_get($totals, 'fees_formatted', data_get($totals, 'fees')),
            'Tax' => data_get($totals, 'tax_formatted', data_get($totals, 'tax')),
            'Total' => data_get($totals, 'grand_total_formatted', data_get($totals, 'total_formatted', data_get($totals, 'total'))),
        ] as $label => $value)
            @if ($value !== null && $value !== '')
                <tr>
                    <td>{{ $label }}</td>
                    <td class="right"><strong>{{ $value }}</strong></td>
                </tr>
            @endif
        @endforeach
    </table>

    <p class="muted" style="margin-top: 18pt;">
        Generated with the native PHP PDF engine for hosts without Chromium. Chromium rendering remains preferred when available.
    </p>
</body>
</html>
