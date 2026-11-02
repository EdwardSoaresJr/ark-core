@if (count($advisorQueuePressure ?? []) > 0)
    <section class="card mb-3" data-report-section="advisor-queue-pressure">
        <div class="card-header">
            <div>
                <h3 class="card-title">Advisor Queue Pressure</h3>
                <div class="card-subtitle">Live operational queue grouped by inferred service advisor - from intake creator or estimate owner.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th scope="col">Advisor</th>
                        <th scope="col" class="text-end">Queue</th>
                        <th scope="col" class="text-end">Approval</th>
                        <th scope="col" class="text-end">Parts</th>
                        <th scope="col" class="text-end">Unpaid</th>
                        <th scope="col">Pressure</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($advisorQueuePressure as $row)
                        <tr>
                            <td class="fw-bold">{{ $row['advisor'] }}</td>
                            <td class="text-end fw-bold ark-money">{{ $row['queue'] }}</td>
                            <td class="text-end ark-money">{{ $row['waiting_approval'] }}</td>
                            <td class="text-end ark-money">{{ $row['parts'] }}</td>
                            <td class="text-end ark-money">{{ $row['unpaid'] }}</td>
                            <td class="small text-secondary">{{ $row['hint'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

<section class="mb-3" aria-labelledby="report-diagnostic-follow-through" data-report-section="diagnostic-follow-through">
    <h3 class="mb-0" id="report-diagnostic-follow-through">Diagnostic → Repair Follow-Through</h3>
    <p class="text-secondary small mb-2">{{ $diagnosticRepairFollowThrough['hint'] }}</p>
    <div class="row row-cards">
        <div class="col-12 col-sm-4">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Diagnostic ROs</div>
                    <div class="h3 mb-0 mt-1 ark-money">{{ $diagnosticRepairFollowThrough['diagnostic_ros'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Repair approved</div>
                    <div class="h3 mb-0 mt-1 ark-money">{{ $diagnosticRepairFollowThrough['repair_follow_through'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card card-sm h-100">
                <div class="card-body">
                    <div class="subheader">Follow-through rate</div>
                    <div class="h3 mb-0 mt-1 ark-money">{{ $diagnosticRepairFollowThrough['rate_label'] }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
