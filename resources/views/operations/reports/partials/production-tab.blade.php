<div class="row row-cards">
    <div class="col-12 col-xl-7">
        <section class="card" data-report-section="operational-pressure">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Operational Pressure</h3>
                    <div class="card-subtitle">What needs owner/operator attention now</div>
                </div>
                <div class="card-actions">
                    <a href="{{ route('operations.index') }}" class="btn btn-sm">Open Queue</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th scope="col">Pressure</th>
                            <th scope="col" class="text-end">Count</th>
                            <th scope="col">Value / Detail</th>
                            <th scope="col">Next Move</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pressureRows as $row)
                            <tr>
                                <td class="fw-bold">{{ $row['pressure'] }}</td>
                                <td class="text-end fw-bold ark-money">{{ $row['count'] }}</td>
                                <td class="small fw-medium text-secondary text-truncate" style="max-width: 20rem">{{ $row['value'] }}</td>
                                <td class="small text-secondary">{{ $row['action'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-5">
        <section class="card mb-3" data-report-section="advisor-throughput">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Advisor Throughput</h3>
                    <div class="card-subtitle">Shop-level until advisor ownership is explicit</div>
                </div>
            </div>
            <div class="list-group list-group-flush">
                @foreach ($advisorRows as $row)
                    <div class="list-group-item">
                        <dl class="row g-2 mb-0">
                            <div class="col-6">
                                <dt class="subheader">Owner</dt>
                                <dd class="fw-bold mb-0">{{ $row['advisor'] }}</dd>
                            </div>
                            <div class="col-6 text-end">
                                <dt class="subheader">ROs</dt>
                                <dd class="fw-bold ark-money mb-0">{{ $row['ro_count'] }}</dd>
                            </div>
                            <div class="col-6">
                                <dt class="subheader">Value</dt>
                                <dd class="ark-money mb-0">{{ $row['value'] }}</dd>
                            </div>
                            <div class="col-6 text-end">
                                <dt class="subheader">Approvals</dt>
                                <dd class="ark-money mb-0">{{ $row['approvals'] }}</dd>
                            </div>
                            <div class="col-6">
                                <dt class="subheader">Deferred</dt>
                                <dd class="ark-money mb-0">{{ $row['deferred'] }}</dd>
                            </div>
                            <div class="col-6 text-end">
                                <dt class="subheader">Unpaid</dt>
                                <dd class="ark-money mb-0">{{ $row['unpaid'] }}</dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card" data-report-section="technician-production">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Technician Production</h3>
                    <div class="card-subtitle">Labor productivity is billed hours ÷ available hours. Labor efficiency is billed hours ÷ actual hours and is not shown here.</div>
                </div>
            </div>
            <div class="list-group list-group-flush">
                @foreach ($technicianRows as $row)
                    <div class="list-group-item">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <div class="fw-bold">{{ $row['technician'] }}</div>
                                <div class="small text-secondary mt-1">{{ $row['assigned'] }} assigned · {{ $row['active'] }} active · {{ $row['blockers'] }} blockers · {{ $row['hours'] }} hrs on board</div>
                                <div class="small fw-medium mt-1">{{ $row['efficiency'] }} labor productivity · billed hours ÷ available hours · {{ $row['efficiency_hint'] }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold ark-money">{{ $row['labor'] }}</div>
                                <div class="small text-secondary ark-money">{{ $row['closed_hours'] }} hrs closed</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</div>
