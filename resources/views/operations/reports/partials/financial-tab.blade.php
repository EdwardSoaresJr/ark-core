@include('operations.reports.partials.payment-reconciliation')

<section class="card mb-3" data-report-section="financial-mix">
    <div class="card-header">
        <div>
            <h3 class="card-title">Financial Mix</h3>
            <div class="card-subtitle">Category dollars are the current approved lines. Margin stays Incomplete data when cost is missing or the lines no longer match the posted invoice.</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Category</th>
                    <th scope="col" class="text-end">Sales</th>
                    <th scope="col" class="text-end">Cost</th>
                    <th scope="col" class="text-end">GP</th>
                    <th scope="col" class="text-end">Margin</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($financialRows as $row)
                    <tr>
                        <td class="fw-bold">{{ $row['category'] }}</td>
                        <td class="text-end fw-bold ark-money">{{ $row['sales'] }}</td>
                        <td class="text-end text-secondary ark-money">{{ $row['cost'] }}</td>
                        <td class="text-end ark-money">{{ $row['gp'] }}</td>
                        <td class="text-end text-secondary ark-money">{{ $row['margin'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="card mb-3" data-report-section="deferred-opportunity">
    <div class="card-header">
        <div>
            <h3 class="card-title">Deferred Work Opportunity</h3>
            <div class="card-subtitle">Future service inventory, not marketing campaigns</div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th scope="col">Bucket</th>
                    <th scope="col" class="text-end">Count</th>
                    <th scope="col" class="text-end">Value</th>
                    <th scope="col">Operational Use</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($opportunityRows as $row)
                    <tr>
                        <td class="fw-bold">{{ $row['bucket'] }}</td>
                        <td class="text-end fw-bold ark-money">{{ $row['count'] }}</td>
                        <td class="text-end ark-money">{{ $row['value'] }}</td>
                        <td class="small text-secondary">{{ $row['next_action'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="card mb-3" data-report-section="recent-posts">
    <div class="card-header">
        <div>
            <h3 class="card-title">Recent Posts</h3>
            <div class="card-subtitle">Posted sales in the selected range</div>
        </div>
    </div>
    <div class="list-group list-group-flush">
        @forelse ($recentClosures as $closure)
            <a href="{{ route('operations.repair-orders.show', $closure['repair_order_id']) }}" class="list-group-item list-group-item-action">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div style="min-width: 0">
                        <div class="fw-bold text-truncate">#{{ $closure['repair_order_id'] }} · {{ $closure['customer'] }}</div>
                        <div class="small text-secondary text-truncate mt-1">{{ $closure['vehicle'] }} · {{ $closure['closed_at']?->format('M j, g:i A') }}</div>
                    </div>
                    <div class="fw-bold ark-money text-nowrap">{{ $closure['total'] }}</div>
                </div>
            </a>
        @empty
            <div class="list-group-item text-center text-secondary py-4">
                No posted repair orders in this range.
            </div>
        @endforelse
    </div>
</section>
