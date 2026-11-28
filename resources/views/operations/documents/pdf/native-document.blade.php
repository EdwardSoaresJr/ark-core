@php
    /** @var array<string, mixed> $snapshot */
    $shop = data_get($snapshot, 'shop', []);
    $customer = data_get($snapshot, 'customer', []);
    $vehicle = data_get($snapshot, 'vehicle', []);
    $repairOrder = data_get($snapshot, 'repair_order', []);
    $concerns = data_get($snapshot, 'concerns', []);
    $totals = data_get($snapshot, 'totals', []);
    $financial = data_get($snapshot, 'financial', []);
    $footer = data_get($snapshot, 'document_footer', []);
    $label = data_get($snapshot, 'pdf_document_label', $document->isInvoice() ? 'Invoice' : 'Estimate');
    $roNumber = data_get($repairOrder, 'repair_order_id', data_get($repairOrder, 'number', data_get($repairOrder, 'id', '—')));
    $prepared = data_get($snapshot, 'generated_at_display')
        ?: data_get($repairOrder, 'created_at')
        ?: data_get($snapshot, 'generated_at');
    if ($prepared instanceof \DateTimeInterface) {
        $prepared = $prepared->format('M j, Y');
    }
    $prepared = is_string($prepared) ? $prepared : '';
    $address = trim(implode(', ', array_filter([
        data_get($shop, 'address_line_1'),
        data_get($shop, 'address_line_2'),
        trim(implode(' ', array_filter([
            data_get($shop, 'city'),
            data_get($shop, 'state'),
            data_get($shop, 'postal_code'),
        ]))),
    ])));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ data_get($shop, 'name', config('app.name')) }} — {{ $label }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        h1 { font-size: 16pt; margin: 0 0 4pt; }
        h2 { font-size: 11pt; margin: 14pt 0 6pt; border-bottom: 1px solid #ccc; padding-bottom: 3pt; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4pt 5pt; vertical-align: top; }
        th { background: #f2f2f2; border-bottom: 1px solid #bbb; text-align: left; font-size: 9pt; }
        td { border-bottom: 1px solid #e5e5e5; }
        .meta td { border: none; padding: 1pt 0; }
        .right { text-align: right; }
        .muted { color: #555; }
        .logo { max-height: 48pt; max-width: 160pt; }
        .totals td { border: none; }
        .totals tr:last-child td { font-size: 11pt; font-weight: bold; border-top: 1px solid #333; padding-top: 6pt; }
        .section { margin-top: 10pt; }
        .footer-block { margin-top: 12pt; font-size: 8.5pt; color: #333; }
        .footer-block p { margin: 0 0 4pt; }
        .pagebreak { page-break-before: always; }
    </style>
</head>
<body>
    <table class="meta">
        <tr>
            <td style="width: 60%;">
                @if (data_get($shop, 'logo_data_uri'))
                    <img class="logo" src="{{ data_get($shop, 'logo_data_uri') }}" alt="">
                @endif
                <h1>{{ data_get($shop, 'name', config('app.name')) }}</h1>
                @if ($address !== '')<div class="muted">{{ $address }}</div>@endif
                @if (data_get($shop, 'phone'))<div class="muted">{{ data_get($shop, 'phone') }}</div>@endif
                @if (data_get($shop, 'email'))<div class="muted">{{ data_get($shop, 'email') }}</div>@endif
                @if (data_get($shop, 'website'))<div class="muted">{{ data_get($shop, 'website') }}</div>@endif
            </td>
            <td class="right" style="width: 40%;">
                <div style="font-size: 14pt; font-weight: bold;">{{ $label }}</div>
                <div><strong>RO #{{ $roNumber }}</strong></div>
                @if ($prepared !== '')<div class="muted">Prepared {{ $prepared }}</div>@endif
            </td>
        </tr>
    </table>

    <h2>Customer / vehicle</h2>
    <table class="meta">
        <tr>
            <td style="width: 55%;">
                <div><strong>{{ data_get($customer, 'name', 'Customer') }}</strong></div>
                @if (data_get($customer, 'phone'))<div class="muted">{{ data_get($customer, 'phone') }}</div>@endif
                @if (data_get($customer, 'email'))<div class="muted">{{ data_get($customer, 'email') }}</div>@endif
            </td>
            <td class="right" style="width: 45%;">
                <div>
                    {{ trim(implode(' ', array_filter([
                        data_get($vehicle, 'year'),
                        data_get($vehicle, 'make'),
                        data_get($vehicle, 'model'),
                    ]))) ?: (string) data_get($vehicle, 'display_name', 'Vehicle') }}
                </div>
                @if (data_get($vehicle, 'vin'))<div class="muted">VIN {{ data_get($vehicle, 'vin') }}</div>@endif
                @if (data_get($vehicle, 'license_plate'))<div class="muted">Plate {{ data_get($vehicle, 'license_plate') }}</div>@endif
                @if (data_get($vehicle, 'mileage_in') || data_get($vehicle, 'odometer_in'))
                    <div class="muted">Mileage {{ data_get($vehicle, 'mileage_in', data_get($vehicle, 'odometer_in')) }}</div>
                @endif
            </td>
        </tr>
    </table>

    <h2>{{ data_get($snapshot, 'pdf_work_column_label', 'Work') }}</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 52%;">Description</th>
                <th class="right" style="width: 12%;">Qty</th>
                <th class="right" style="width: 18%;">Amount</th>
                <th class="right" style="width: 18%;">Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($concerns as $concern)
                <tr>
                    <td colspan="4"><strong>{{ data_get($concern, 'title', data_get($concern, 'name', 'Concern')) }}</strong></td>
                </tr>
                @php
                    $lines = data_get($concern, 'charge_lines');
                    if (! is_array($lines) || $lines === []) {
                        $lines = data_get($concern, 'lines', data_get($concern, 'line_items', []));
                    }
                @endphp
                @foreach ((array) $lines as $line)
                    <tr>
                        <td>{{ data_get($line, 'description', data_get($line, 'customer_description', data_get($line, 'label', 'Item'))) }}</td>
                        <td class="right">{{ data_get($line, 'quantity_label', data_get($line, 'quantity', '')) }}</td>
                        <td class="right">{{ data_get($line, 'amount', data_get($line, 'amount_formatted', data_get($line, 'total_formatted', data_get($line, 'total', '')))) }}</td>
                        <td class="right muted">{{ data_get($line, 'detail', data_get($line, 'type_label', '')) }}</td>
                    </tr>
                    @if (data_get($line, 'standing_discount') || data_get($line, 'standing_discount_cents'))
                        <tr>
                            <td class="muted" colspan="2">Discount</td>
                            <td class="right muted">{{ data_get($line, 'standing_discount', data_get($line, 'standing_discount_cents')) }}</td>
                            <td></td>
                        </tr>
                    @endif
                @endforeach
                @if (data_get($concern, 'charge_subtotal') || data_get($concern, 'subtotal'))
                    <tr>
                        <td colspan="2" class="right muted">Concern subtotal</td>
                        <td class="right">{{ data_get($concern, 'charge_subtotal', data_get($concern, 'subtotal')) }}</td>
                        <td></td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="4" class="muted">No line items on this document.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Totals</h2>
    <table class="totals">
        @foreach ([
            'Labor' => data_get($totals, 'labor_formatted', data_get($totals, 'labor')),
            'Parts' => data_get($totals, 'parts_formatted', data_get($totals, 'parts')),
            'Fees' => data_get($totals, 'fees_formatted', data_get($totals, 'fees')),
            data_get($totals, 'standing_discount_label', 'Discounts') => data_get($totals, 'standing_discount_formatted', data_get($totals, 'standing_discount')),
            data_get($totals, 'tax_label', 'Tax') => data_get($totals, 'tax_formatted', data_get($totals, 'tax')),
            'Total' => data_get($totals, 'total_formatted', data_get($totals, 'grand_total_formatted', data_get($totals, 'total'))),
        ] as $rowLabel => $value)
            @if ($value !== null && $value !== '')
                <tr>
                    <td class="right" style="width: 70%;">{{ $rowLabel }}</td>
                    <td class="right" style="width: 30%;">{{ $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    @if (is_array($financial) && $financial !== [])
        <h2>Payments &amp; balance</h2>
        <table class="totals">
            @foreach ([
                'Deposits applied' => data_get($financial, 'deposits_applied', data_get($financial, 'deposits_applied_cents')),
                'Payments applied' => data_get($financial, 'payments_applied', data_get($financial, 'payments_applied_cents')),
                'Credits applied' => data_get($financial, 'credits_applied', data_get($financial, 'credits_applied_cents')),
                'Adjustments' => data_get($financial, 'adjustments', data_get($financial, 'adjustments_cents')),
                'Write-offs' => data_get($financial, 'write_offs', data_get($financial, 'write_offs_cents')),
                'Balance due' => data_get($financial, 'balance_due', data_get($financial, 'balance_due_cents')),
                'Overpaid' => data_get($financial, 'overpaid', data_get($financial, 'overpaid_cents')),
            ] as $rowLabel => $value)
                @if ($value !== null && $value !== '' && $value !== 0 && $value !== '0')
                    <tr>
                        <td class="right" style="width: 70%;">{{ $rowLabel }}</td>
                        <td class="right" style="width: 30%;">{{ $value }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
        @foreach ((array) data_get($financial, 'entries', []) as $entry)
            <div class="muted">
                {{ data_get($entry, 'type_label', data_get($entry, 'method_label', 'Entry')) }}
                {{ data_get($entry, 'amount', '') }}
                {{ data_get($entry, 'recorded_at_display', '') }}
            </div>
        @endforeach
    @endif

    @php
        $authorization = data_get($footer, 'authorization', data_get($snapshot, 'documents.authorization_language', []));
        if (is_string($authorization) && $authorization !== '') {
            $authorization = [$authorization];
        }
        $important = data_get($footer, 'important_information', []);
        $concernTerms = data_get($footer, 'concern_terms', data_get($snapshot, 'documents.concern_terms', []));
    @endphp

    @if (is_array($authorization) && $authorization !== [])
        <div class="footer-block">
            <strong>Authorization</strong>
            @foreach ($authorization as $line)
                <p>{{ is_array($line) ? (data_get($line, 'text', json_encode($line))) : $line }}</p>
            @endforeach
        </div>
    @endif

    @if (is_array($important) && $important !== [])
        <div class="footer-block">
            <strong>Important information</strong>
            @foreach ($important as $line)
                <p>{{ is_array($line) ? (data_get($line, 'text', json_encode($line))) : $line }}</p>
            @endforeach
        </div>
    @endif

    @if (is_array($concernTerms) && $concernTerms !== [])
        <div class="footer-block">
            <strong>Warranty &amp; terms</strong>
            @foreach ($concernTerms as $term)
                <p>
                    <strong>{{ data_get($term, 'billing_class_name', data_get($term, 'concern_summary', 'Terms')) }}</strong>
                    {{ data_get($term, 'warranty.summary', data_get($term, 'terms', '')) }}
                    {{ data_get($term, 'warranty.third_party_disclaimer', '') }}
                </p>
            @endforeach
        </div>
    @endif
</body>
</html>
