@include('operations.reports.partials.shop-behavior-pulse')

@include('operations.reports.partials.advisor-diagnostic-signals')

<section class="card mb-3" data-report-section="operational-truth">
    <div class="card-header">
        <div>
            <h3 class="card-title">Operational Truth</h3>
            <div class="card-subtitle">Where is work accumulating? Prior = already in bucket at period start.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Bucket</th>
                    <th scope="col" class="text-end">Current</th>
                    <th scope="col" class="text-end">Period Start</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($operationalTruth['buckets'] as $row)
                    <tr>
                        <td class="fw-bold">{{ $row['label'] }}</td>
                        <td class="text-end fw-bold ark-money">{{ $row['current'] }}</td>
                        <td class="text-end text-secondary ark-money">{{ $row['prior_period'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (count($operationalTruth['drilldown']) > 0)
        <div class="card-body border-top py-2">
            <div class="subheader">Drill-down</div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th scope="col">RO</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Vehicle</th>
                        <th scope="col">Bucket</th>
                        <th scope="col">Status</th>
                        <th scope="col">Age</th>
                        <th scope="col">Last Activity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($operationalTruth['drilldown'] as $row)
                        <tr>
                            <td class="fw-bold">
                                <a href="{{ $row['url'] }}">#{{ $row['repair_order_id'] }}</a>
                            </td>
                            <td>{{ $row['customer'] }}</td>
                            <td class="text-secondary text-truncate" style="max-width: 20rem">{{ $row['vehicle'] }}</td>
                            <td class="small text-secondary">{{ $row['bucket'] }}</td>
                            <td class="small text-secondary">{{ $row['status'] }}</td>
                            <td class="fw-bold ark-money">{{ $row['age'] }}</td>
                            <td class="small text-secondary">{{ $row['last_activity'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="card mb-3" data-report-section="approval-momentum">
    <div class="card-header">
        <div>
            <h3 class="card-title">Approval Momentum</h3>
            <div class="card-subtitle">Where are estimates dying? Funnel uses ROs opened in range; awaiting and aging are live queue.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Stage</th>
                    <th scope="col" class="text-end">Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($approvalMomentum['stages'] as $row)
                    <tr>
                        <td class="fw-bold">{{ $row['label'] }}</td>
                        <td class="text-end fw-bold ark-money">{{ $row['count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (count($approvalMomentum['drilldown']) > 0)
        <div class="card-body border-top py-2">
            <div class="subheader">Drill-down</div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th scope="col">RO</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Sent</th>
                        <th scope="col">Viewed</th>
                        <th scope="col">Last Activity</th>
                        <th scope="col">Age</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($approvalMomentum['drilldown'] as $row)
                        <tr>
                            <td class="fw-bold">
                                <a href="{{ $row['url'] }}">#{{ $row['repair_order_id'] }}</a>
                            </td>
                            <td>{{ $row['customer'] }}</td>
                            <td class="small text-secondary">{{ $row['sent'] }}</td>
                            <td class="small text-secondary">{{ $row['viewed'] }}</td>
                            <td class="small text-secondary">{{ $row['last_activity'] }}</td>
                            <td class="fw-bold ark-money">{{ $row['age'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="card mb-3" data-report-section="labor-liability">
    <div class="card-header">
        <div>
            <h3 class="card-title">Labor Liability</h3>
            <div class="card-subtitle">Where did labor capacity go? Approved labor on ROs opened in range; value uses shop opportunity rate when sell is $0.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Category</th>
                    <th scope="col" class="text-end">Hours</th>
                    <th scope="col" class="text-end">Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($liability['rows'] as $row)
                    <tr>
                        <td class="fw-bold">{{ $row['category'] }}</td>
                        <td class="text-end fw-bold ark-money">{{ $row['hours'] }}</td>
                        <td class="text-end ark-money">{{ $row['value'] }}</td>
                    </tr>
                @endforeach
                <tr class="table-active fw-bold">
                    <td>Total Non-Billable</td>
                    <td class="text-end ark-money">{{ $liability['total_hours'] }}</td>
                    <td class="text-end ark-money">{{ $liability['total_value'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="card mb-3" data-report-section="recommendation-conversion">
    <div class="card-header">
        <div>
            <h3 class="card-title">Recommendation Conversion</h3>
            <div class="card-subtitle">What recommendations are converting? Concerns on ROs opened in range, grouped by priority.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Priority</th>
                    <th scope="col" class="text-end">Recommended</th>
                    <th scope="col" class="text-end">Approved</th>
                    <th scope="col" class="text-end">Deferred</th>
                    <th scope="col" class="text-end">Deferred $</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recommendationConversion as $row)
                    <tr>
                        <td class="fw-bold">{{ $row['intent'] }}</td>
                        <td class="text-end fw-bold ark-money">{{ $row['recommended'] }}</td>
                        <td class="text-end ark-money">{{ $row['approved'] }}</td>
                        <td class="text-end ark-money">{{ $row['deferred'] }}</td>
                        <td class="text-end fw-bold ark-money">{{ $row['deferred_value'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
