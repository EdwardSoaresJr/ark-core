@php
    $summary = $ownerPlSummary;
    $toneText = fn (?string $tone, string $fallback = 'text-secondary'): string => match ($tone) {
        'good' => 'text-green',
        'warn' => 'text-warning',
        default => $fallback,
    };
@endphp

<section class="card mb-3" data-report-section="owner-pl">
    <div class="card-header">
        <div>
            <h3 class="card-title">Owner P&amp;L</h3>
            <div class="card-subtitle">
                {{ $summary['posted_count'] }} posted RO{{ $summary['posted_count'] === 1 ? '' : 's' }} · {{ $summary['range_days'] }} days in range.
                {{ $summary['disclaimer'] }}
                Edit assumptions in
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}">Owner Targets &amp; Reporting</a>
                and
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'overhead']) }}">Shop Overhead</a>.
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Line</th>
                    <th scope="col" class="text-end">Amount</th>
                    <th scope="col" class="text-end">% rev</th>
                    <th scope="col">Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['pl_lines'] as $row)
                    @php $emphasis = $row['emphasis'] ?? 'normal'; @endphp
                    <tr @class(['table-active' => in_array($emphasis, ['subtotal', 'total'], true)]) data-report-emphasis="{{ $emphasis }}">
                        <td @class([
                            'fw-bold' => ! $row['indent'],
                            'ps-4 text-secondary' => $row['indent'],
                            'fs-3' => $emphasis === 'total',
                        ])>{{ $row['label'] }}</td>
                        <td @class([
                            'text-end ark-money',
                            'fw-bold' => in_array($emphasis, ['subtotal', 'total'], true),
                            $toneText($row['tone'] ?? null, '') => true,
                        ])>{{ $row['amount'] }}</td>
                        <td class="text-end text-secondary ark-money">{{ $row['percent'] ?? '-' }}</td>
                        <td class="small text-secondary">{{ $row['note'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@if ($summary['benchmark'] !== null)
    <section class="mb-3" aria-labelledby="report-net-benchmark" data-report-section="net-benchmark">
        <h3 class="mb-0" id="report-net-benchmark">Net profit benchmark</h3>
        <p class="text-secondary small mb-2">
            {{ $summary['benchmark']['net_target_percent'] }}% net on service revenue - after prorated fixed costs and estimated tax reserves.
        </p>
        <div class="row row-cards">
            <div class="col-6 col-sm-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">{{ $summary['benchmark']['net_target_percent'] }}% target</div>
                        <div class="h3 mb-0 mt-1 ark-money">{{ $summary['benchmark']['net_target_label'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Est. net after reserves</div>
                        <div class="h3 mb-0 mt-1 ark-money">{{ $summary['benchmark']['estimated_net_label'] ?? 'Configure fixed costs' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Gap to target</div>
                        <div class="h3 mb-0 mt-1 ark-money {{ $toneText($summary['benchmark']['tone'] ?? null, '') }}">{{ $summary['benchmark']['gap_label'] ?? '-' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="card card-sm h-100">
                    <div class="card-body">
                        <div class="subheader">Posture</div>
                        <div class="small fw-medium mt-1 {{ $toneText($summary['benchmark']['tone'] ?? null) }}">{{ $summary['benchmark']['posture'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

<section class="card mb-3" data-report-section="tax-remittance">
    <div class="card-header">
        <div>
            <h3 class="card-title">Tax &amp; remittance posture</h3>
            <div class="card-subtitle">Sales tax is from posted RO lines. Payroll and income lines are planning reserves - not filing amounts.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Item</th>
                    <th scope="col" class="text-end">Amount</th>
                    <th scope="col">Source</th>
                    <th scope="col">Note</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['tax_lines'] as $row)
                    <tr @class(['table-active' => str_starts_with($row['label'], 'Total tax')])>
                        <td class="fw-bold">{{ $row['label'] }}</td>
                        <td class="text-end fw-bold ark-money">{{ $row['amount'] }}</td>
                        <td class="small">{{ $row['source'] }}</td>
                        <td class="small text-secondary">{{ $row['note'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@if ($breakEvenSummary !== null)
    <div class="alert alert-info mb-0" data-report-section="break-even-cross-check">
        <strong>Break-even cross-check:</strong>
        posted GP {{ $breakEvenSummary['gross_profit_label'] }} vs prorated fixed {{ $breakEvenSummary['prorated_fixed_label'] }}
        ({{ $breakEvenSummary['surplus_label'] }}).
        <a href="{{ route('operations.reports.operational', ['from' => \App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($from), 'to' => \App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($to), 'tab' => 'margin-health']) }}">Margin Health</a>
        has the full margin rows.
    </div>
@endif
