@php
    /** @var \App\Ark\Operations\Reports\EndOfDayReportProjection $eod */
@endphp

<div data-eod-report>
    <section class="card mb-3" aria-labelledby="eod-title" data-eod-header>
        <div class="card-body p-2 p-md-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md">
                    <div class="subheader">End of Day Report</div>
                    <h2 class="mb-1" id="eod-title">{{ $eod->rangeLabel }}</h2>
                    <p class="small text-secondary mb-0">Posted sales truth · ROs with <code>posted_at</code> in range. Cash collected may differ - see reconciliation below.</p>
                </div>
                <div class="col-12 col-md-auto">
                    <form method="GET" action="{{ $dateFormAction ?? route('operations.owner.day-review') }}">
                        <label class="form-label" for="bookend-date">Shop day</label>
                        <input
                            id="bookend-date"
                            type="date"
                            name="date"
                            class="form-control"
                            value="{{ $eod->fromDate === $eod->toDate ? $eod->fromDate : $eod->fromDate }}"
                            min="{{ App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString(App\Ark\Operations\Reports\OperationalReportDateScope::trustworthyDataStartsAt()) }}"
                            onchange="this.form.submit()"
                        >
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-3" aria-labelledby="eod-sales-effectiveness" data-eod-section="sales-effectiveness">
        <h3 id="eod-sales-effectiveness" class="mb-2">How effective is your shop at selling work?</h3>
        <div class="row row-cards">
            @foreach ($eod->salesEffectiveness as $metric)
                <div class="col-6 col-md">
                    <div class="card card-sm h-100" data-eod-metric>
                        <div class="card-body">
                            <div class="subheader">{{ $metric['label'] }}</div>
                            <div class="h2 mb-0 mt-1 ark-money">{{ $metric['value'] }}</div>
                            @if (filled($metric['hint'] ?? null))
                                <div class="small text-secondary mt-1">{{ $metric['hint'] }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-3" aria-labelledby="eod-shop-metrics" data-eod-section="shop-metrics">
        <h3 id="eod-shop-metrics" class="mb-2">What are your overall shop metrics?</h3>
        <div class="row row-cards">
            @foreach ($eod->shopMetrics as $metric)
                <div class="col-6 col-md">
                    <div class="card card-sm h-100" data-eod-metric>
                        <div class="card-body">
                            <div class="subheader">{{ $metric['label'] }}</div>
                            <div class="h2 mb-0 mt-1 ark-money">{{ $metric['value'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="row row-cards mb-3">
        <div class="col-12 col-lg-6">
            <section class="card h-100" aria-labelledby="eod-ro-summary" data-eod-section="ro-summary">
                <div class="card-header">
                    <h3 id="eod-ro-summary" class="card-title">RO Summary</h3>
                </div>
                <div class="table-responsive position-relative">
                    <table class="table table-vcenter card-table">
                        <tbody>
                            @foreach ($eod->roSummary as $row)
                                @php $tone = $row['tone'] ?? null; @endphp
                                <tr @class(['table-active fw-bold' => $tone === 'total']) data-eod-tone="{{ $tone ?? 'normal' }}">
                                    <th scope="row" @class(['fw-normal' => $tone !== 'total', 'text-danger' => $tone === 'subtract'])>{{ $row['label'] }}</th>
                                    <td @class(['text-end ark-money', 'text-danger' => $tone === 'subtract'])>{{ $row['value'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-6">
            <section class="card h-100" aria-labelledby="eod-sales-breakdown" data-eod-section="sales-breakdown">
                <div class="card-header">
                    <h3 id="eod-sales-breakdown" class="card-title">Sales after Discounts</h3>
                </div>
                <div class="table-responsive position-relative">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th scope="col"><span class="visually-hidden">Category</span></th>
                                <th scope="col" class="text-end">Non-taxable</th>
                                <th scope="col" class="text-end">Taxable</th>
                                <th scope="col" class="text-end text-warning">Net Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($eod->salesBreakdown as $row)
                                <tr>
                                    <th scope="row" class="fw-normal">{{ $row['category'] }}</th>
                                    <td class="text-end ark-money">{{ $row['non_taxable'] }}</td>
                                    <td class="text-end ark-money">{{ $row['taxable'] }}</td>
                                    <td class="text-end fw-bold ark-money">{{ $row['net'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <section class="card mb-3" aria-label="Cash reconciliation" data-eod-reconcile>
        <div class="card-body p-2 p-md-3 d-flex flex-wrap align-items-center gap-3">
            <dl class="d-flex flex-wrap align-items-center gap-3 gap-md-4 mb-0">
                <div>
                    <dt class="subheader">Invoice total</dt>
                    <dd class="fw-bold ark-money mb-0">{{ $eod->reconciliation['sales_posted'] }}</dd>
                </div>
                <div>
                    <dt class="subheader">Write-offs</dt>
                    <dd class="fw-bold ark-money mb-0">{{ $eod->reconciliation['write_offs'] }}</dd>
                </div>
                <div>
                    <dt class="subheader">Cash Collected</dt>
                    <dd class="fw-bold ark-money mb-0">{{ $eod->reconciliation['cash_collected'] }}</dd>
                </div>
                <div data-eod-reconciles="{{ $eod->reconciliation['reconciles'] ? 'yes' : 'no' }}">
                    <dt class="subheader">{{ $eod->reconciliation['reconciles'] ? 'Reconciles' : 'Delta' }}</dt>
                    <dd @class([
                        'fw-bold ark-money mb-0',
                        'text-green' => $eod->reconciliation['reconciles'],
                        'text-warning' => ! $eod->reconciliation['reconciles'],
                    ])>{{ $eod->reconciliation['reconciles'] ? 'Yes' : $eod->reconciliation['delta_label'] }}</dd>
                </div>
            </dl>
            <a href="{{ $eod->financialUrl }}" class="ms-md-auto">Full payments reconciliation →</a>
        </div>
    </section>
</div>
