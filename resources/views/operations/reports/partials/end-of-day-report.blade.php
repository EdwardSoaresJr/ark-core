@php
    /** @var \App\Ark\Operations\Reports\EndOfDayReportProjection $eod */
@endphp

<div class="ops-eod-report">
    <header class="ops-eod-report__header">
        <div>
            <p class="ops-eod-report__eyebrow">End of Day Report</p>
            <h1 class="ops-eod-report__title">{{ $eod->rangeLabel }}</h1>
            <p class="ops-eod-report__note">Posted sales truth · ROs with <code class="text-[10px]">posted_at</code> in range. Cash collected may differ — see reconciliation below.</p>
        </div>
        <form method="GET" action="{{ $dateFormAction ?? route('operations.owner.day-review') }}" class="ops-eod-report__date">
            <label class="ops-eod-report__date-label" for="bookend-date">Shop day</label>
            <input
                id="bookend-date"
                type="date"
                name="date"
                class="ops-eod-report__date-input"
                value="{{ $eod->fromDate === $eod->toDate ? $eod->fromDate : $eod->fromDate }}"
                min="{{ App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString(App\Ark\Operations\Reports\OperationalReportDateScope::trustworthyDataStartsAt()) }}"
                onchange="this.form.submit()"
            >
        </form>
    </header>

    <section class="ops-eod-report__section" aria-labelledby="eod-sales-effectiveness">
        <h2 id="eod-sales-effectiveness" class="ops-eod-report__section-title">How effective is your shop at selling work?</h2>
        <div class="ops-eod-report__metric-grid ops-eod-report__metric-grid--5">
            @foreach ($eod->salesEffectiveness as $metric)
                <div class="ops-eod-report__metric">
                    <p class="ops-eod-report__metric-label">{{ $metric['label'] }}</p>
                    <p class="ops-eod-report__metric-value">{{ $metric['value'] }}</p>
                    @if (filled($metric['hint'] ?? null))
                        <p class="ops-eod-report__metric-hint">{{ $metric['hint'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="ops-eod-report__section" aria-labelledby="eod-shop-metrics">
        <h2 id="eod-shop-metrics" class="ops-eod-report__section-title">What are your overall shop metrics?</h2>
        <div class="ops-eod-report__metric-grid ops-eod-report__metric-grid--5">
            @foreach ($eod->shopMetrics as $metric)
                <div class="ops-eod-report__metric">
                    <p class="ops-eod-report__metric-label">{{ $metric['label'] }}</p>
                    <p class="ops-eod-report__metric-value">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <div class="ops-eod-report__tables">
        <section class="ops-eod-report__section ops-eod-report__section--table" aria-labelledby="eod-ro-summary">
            <h2 id="eod-ro-summary" class="ops-eod-report__section-title">RO Summary</h2>
            <table class="ops-eod-report__table">
                <tbody>
                    @foreach ($eod->roSummary as $row)
                        <tr @class(['ops-eod-report__table-row--total' => ($row['tone'] ?? null) === 'total'])>
                            <th scope="row" @class(['ops-eod-report__table-label--subtract' => ($row['tone'] ?? null) === 'subtract'])>{{ $row['label'] }}</th>
                            <td @class([
                                'ops-eod-report__table-value',
                                'ops-eod-report__table-value--subtract' => ($row['tone'] ?? null) === 'subtract',
                                'ops-eod-report__table-value--total' => ($row['tone'] ?? null) === 'total',
                            ])>{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="ops-eod-report__section ops-eod-report__section--table" aria-labelledby="eod-sales-breakdown">
            <h2 id="eod-sales-breakdown" class="ops-eod-report__section-title">Sales after Discounts</h2>
            <table class="ops-eod-report__table">
                <thead>
                    <tr>
                        <th scope="col"></th>
                        <th scope="col" class="ops-eod-report__table-head">Non-taxable</th>
                        <th scope="col" class="ops-eod-report__table-head">Taxable</th>
                        <th scope="col" class="ops-eod-report__table-head ops-eod-report__table-head--net">Net Sales</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($eod->salesBreakdown as $row)
                        <tr>
                            <th scope="row">{{ $row['category'] }}</th>
                            <td class="ops-eod-report__table-value">{{ $row['non_taxable'] }}</td>
                            <td class="ops-eod-report__table-value">{{ $row['taxable'] }}</td>
                            <td class="ops-eod-report__table-value ops-eod-report__table-value--net">{{ $row['net'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>

    <section class="ops-eod-report__reconcile" aria-label="Cash reconciliation">
        <div class="ops-eod-report__reconcile-row">
            <span>Sales Posted</span>
            <strong>{{ $eod->reconciliation['sales_posted'] }}</strong>
        </div>
        <div class="ops-eod-report__reconcile-row">
            <span>Cash Collected</span>
            <strong>{{ $eod->reconciliation['cash_collected'] }}</strong>
        </div>
        <div @class([
            'ops-eod-report__reconcile-row',
            'ops-eod-report__reconcile-row--ok' => $eod->reconciliation['reconciles'],
            'ops-eod-report__reconcile-row--warn' => ! $eod->reconciliation['reconciles'],
        ])>
            <span>{{ $eod->reconciliation['reconciles'] ? 'Reconciles' : 'Delta' }}</span>
            <strong>{{ $eod->reconciliation['reconciles'] ? 'Yes' : $eod->reconciliation['delta_label'] }}</strong>
        </div>
        <a href="{{ $eod->financialUrl }}" class="ops-eod-report__reconcile-link">Full payments reconciliation →</a>
    </section>
</div>
