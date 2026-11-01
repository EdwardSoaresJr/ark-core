@php
    $a = $assist;
    $money = fn (?int $cents): string => $cents === null ? '-' : '$'.number_format($cents / 100, 2);
@endphp

<x-operations.app :title="'Production · '.$technician->name" ui="tabler">
    <x-tabler.page
        layout="fluid"
        :title="$technician->name"
        pretitle="Technician production"
        :subtitle="$from.' → '.$to"
        actions-label="Technician shortcuts"
        stack-actions
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.owner.technician-production.index', ['from' => $from, 'to' => $to])" icon="arrow-left" label="All technicians" />
            <x-tabler.page-action :href="route('operations.time-clock.staff', $technician)" icon="clock" label="Time clock" />
        </x-slot:actions>

        <div class="card mb-3">
            <div class="card-body p-2 p-md-3">
                <form method="GET" action="{{ route('operations.owner.technician-production.show', $technician) }}" class="row g-2 align-items-end" data-technician-production-range>
                    <div class="col-6 col-sm-auto">
                        <label class="form-label" for="from">From</label>
                        <input id="from" name="from" type="date" value="{{ $from }}" class="form-control">
                    </div>
                    <div class="col-6 col-sm-auto">
                        <label class="form-label" for="to">To</label>
                        <input id="to" name="to" type="date" value="{{ $to }}" class="form-control">
                    </div>
                    <div class="col-12 col-sm-auto">
                        <button type="submit" class="btn w-100">Update</button>
                    </div>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success mb-3">{{ session('status') }}</div>
        @endif

        @if ($a['overtime_review_required'])
            <div class="alert alert-warning mb-3" data-technician-overtime-review>
                <h4 class="alert-title">Overtime review required</h4>
                <div>{{ $a['overtime_warning']['message'] }}</div>
                <ul class="small mt-2 mb-0" style="list-style: disc">
                    @foreach ($a['overtime_warning']['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($a['history_unavailable'])
            <div class="alert alert-info mb-3" data-technician-history-unavailable>
                <h4 class="alert-title">Production history unavailable for this period</h4>
                <div class="small">{{ $a['history_unavailable_reason'] }}</div>
                <div class="small text-secondary mt-2">Zero and unknown are different. ARK will not pretend recognized flag was 0.0 before recognition authority existed.</div>
            </div>
        @else
            @if ($a['history_partial'] ?? false)
                <div class="alert alert-info small mb-3">{{ $a['history_partial_note'] }}</div>
            @endif

            <section class="mb-3" aria-labelledby="technician-production-picture" data-technician-production-picture>
                <h3 class="mb-0" id="technician-production-picture">Production picture</h3>
                <p class="small text-secondary mb-2">Why is recognized flag low - pending still sitting, or not much production?</p>
                <div class="row row-cards">
                    <div class="col-12 col-sm-4">
                        <div class="card card-sm h-100">
                            <div class="card-body">
                                <div class="subheader">Clock hours</div>
                                <div class="h1 mb-0 mt-1 ark-money">{{ number_format((float) $a['clock_hours'], 1) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="card card-sm h-100">
                            <div class="card-body">
                                <div class="subheader">Recognized flag</div>
                                <div class="h1 mb-0 mt-1 ark-money">{{ number_format((float) $a['recognized_flag_hours'], 1) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="card card-sm h-100">
                            <div class="card-body">
                                <div class="subheader">Pending flag</div>
                                <div class="h1 mb-0 mt-1 ark-money">{{ number_format((float) $a['pending_flag_hours'], 1) }}</div>
                                <div class="small text-secondary mt-1">Not earned · not owed</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="card card-sm h-100">
                            <div class="card-body">
                                <div class="subheader">Recognized / clock</div>
                                <div class="h3 mb-0 mt-1 ark-money">{{ $a['recognized_efficiency_percent'] === null ? '-' : number_format((float) $a['recognized_efficiency_percent'], 1).'%' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="card card-sm h-100">
                            <div class="card-body">
                                <div class="subheader">{{ $a['production_in_view_label'] }}</div>
                                <div class="h3 mb-0 mt-1 ark-money">{{ $a['production_in_view_percent'] === null ? '-' : number_format((float) $a['production_in_view_percent'], 1).'%' }}</div>
                                <div class="small text-secondary mt-1">Includes pending - not earned production</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            @if ($a['wip_impact'] ?? null)
                <div class="alert alert-info mb-3">{{ $a['wip_impact']['message'] }}</div>
            @endif

            <section class="card mb-3" data-technician-compensation-assist>
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Base compensation assist</h3>
                        <div class="card-subtitle">Management estimate only - not Gross Pay, Paycheck, or Payroll Due.</div>
                    </div>
                </div>
                <dl class="list-group list-group-flush mb-0">
                    <div class="list-group-item d-flex justify-content-between gap-3">
                        <dt class="fw-normal text-secondary">Recognized flag earnings</dt>
                        <dd class="fw-medium ark-money mb-0">{{ $money($a['recognized_earnings_cents']) }}</dd>
                    </div>
                    <div class="list-group-item d-flex justify-content-between gap-3">
                        <dt class="fw-normal text-secondary">Floor requirement</dt>
                        <dd class="fw-medium ark-money mb-0">{{ $money($a['floor_requirement_cents']) }}</dd>
                    </div>
                    <div class="list-group-item d-flex justify-content-between gap-3">
                        <dt class="fw-normal text-secondary">Floor exposure</dt>
                        <dd class="fw-medium ark-money mb-0">{{ $money($a['floor_exposure_cents']) }}</dd>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                        <dt class="fw-bold">Base compensation assist</dt>
                        <dd class="h3 ark-money mb-0">{{ $money($a['base_compensation_assist_cents']) }}</dd>
                    </div>
                </dl>
                @if ($a['pending_flag_hours'] > 0)
                    <div class="card-footer small">
                        <p class="mb-0"><span class="fw-medium">{{ number_format((float) $a['pending_flag_hours'], 1) }} pending flag hr</span>
                            @if ($a['pending_value_cents'] !== null)
                                · about {{ $money($a['pending_value_cents']) }} of potential flag production sitting unfinished at the current flag rate.
                            @endif
                        </p>
                        <p class="text-secondary mt-1 mb-0">Not wages owed. Not unpaid compensation. Not guaranteed future pay. Pending does not reduce floor exposure.</p>
                    </div>
                @endif
            </section>
        @endif

        <section class="card mb-3" data-technician-compensable-hours>
            <div class="card-header">
                <div>
                    <h3 class="card-title">Compensable hours</h3>
                    <div class="card-subtitle">Manual override · locks punch-derived hours for that day · leave blank to clear override and restore punch hours</div>
                </div>
            </div>
            <form method="POST" action="{{ route('operations.owner.technician-production.time', $technician) }}">
                @csrf
                <input type="hidden" name="from" value="{{ $from }}">
                <input type="hidden" name="to" value="{{ $to }}">
                <div class="card-body p-2 p-md-3">
                    <div class="row g-2">
                        @foreach ($weekDates as $date)
                            @php
                                $label = \Illuminate\Support\Carbon::parse($date)->format('D n/j');
                                $val = $timeByDate[$date] ?? null;
                            @endphp
                            <div class="col-6 col-sm-3 col-md">
                                <label class="form-label" for="hours-{{ $date }}">{{ $label }}</label>
                                <input
                                    id="hours-{{ $date }}"
                                    type="number"
                                    name="hours[{{ $date }}]"
                                    value="{{ $val !== null ? number_format((float) $val, 2, '.', '') : '' }}"
                                    min="0"
                                    max="24"
                                    step="0.25"
                                    class="form-control ark-money"
                                    placeholder="0"
                                >
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary">Save hours</button>
                </div>
            </form>
        </section>

        <details class="card" open data-technician-audit-trail>
            <summary class="card-body py-2 fw-bold">Show me · audit trail</summary>
            <div class="card-body border-top p-2 p-md-3">
                <div class="mb-3">
                    <div class="subheader">Time</div>
                    <ul class="list-group mt-1">
                        @foreach ($a['daily_time'] as $day)
                            <li class="list-group-item d-flex flex-wrap justify-content-between gap-2 py-2 small">
                                <span>{{ $day['weekday'] }} {{ $day['date'] }}</span>
                                <span class="ark-money">{{ $day['compensable_hours'] === null ? '-' : number_format((float) $day['compensable_hours'], 2).' hr' }}
                                    @if ($day['floor_rate_dollars'] !== null)
                                        · floor ${{ number_format((float) $day['floor_rate_dollars'], 2) }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @unless ($a['history_unavailable'])
                    <div class="mb-3">
                        <div class="subheader">Recognized flag</div>
                        @forelse ($a['recognized_lines'] as $line)
                            <div class="card card-sm mt-1">
                                <div class="card-body py-2 small">
                                    <div class="fw-medium">RO {{ $line['repair_order_id'] }} · {{ $line['vehicle'] }} · {{ number_format((float) $line['flag_hours'], 2) }} hr</div>
                                    <div class="text-secondary">{{ $line['concern'] }} · {{ $line['labor_description'] }}</div>
                                    <div class="text-secondary ark-money">{{ $line['recognized_date'] }} · rate {{ $line['flag_rate_dollars'] === null ? '-' : '$'.number_format((float) $line['flag_rate_dollars'], 2) }} · {{ $money($line['earnings_cents']) }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="small text-secondary mt-1 mb-0">No recognized flag in this period.</p>
                        @endforelse
                    </div>

                    <div class="mb-3">
                        <div class="subheader">Pending flag</div>
                        @forelse ($a['pending_lines'] as $line)
                            <div class="card card-sm mt-1">
                                <div class="card-body py-2 small">
                                    <div class="fw-medium">RO {{ $line['repair_order_id'] }} · {{ $line['vehicle'] }} · {{ number_format((float) $line['flag_hours'], 2) }} hr</div>
                                    <div class="text-secondary">{{ $line['concern'] }} · {{ $line['labor_description'] }}</div>
                                    <div class="text-secondary">{{ $line['production_status_label'] ?? '-' }} · {{ $line['attribution_source'] }}</div>
                                </div>
                            </div>
                        @empty
                            <p class="small text-secondary mt-1 mb-0">No pending flag attributed to this technician.</p>
                        @endforelse
                    </div>

                    @if (count($a['unassigned_pending_lines'] ?? []) > 0)
                        <div class="mb-3">
                            <div class="subheader text-warning">Unassigned pending (not counted to a technician)</div>
                            @foreach ($a['unassigned_pending_lines'] as $line)
                                <div class="card card-sm mt-1">
                                    <div class="card-status-start bg-yellow"></div>
                                    <div class="card-body py-2 small">
                                        <div class="fw-medium">RO {{ $line['repair_order_id'] }} · {{ $line['vehicle'] }} · {{ number_format((float) $line['flag_hours'], 2) }} hr</div>
                                        <div class="text-secondary">{{ $line['labor_description'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endunless

                <div>
                    <div class="subheader">Policy</div>
                    <p class="small mt-1 mb-0">{{ $a['policy']['recognition_policy_label'] }}</p>
                    <p class="small text-secondary mt-1 mb-0">{{ $a['policy']['explanation'] }}</p>
                    <p class="small text-secondary mt-1 mb-0">{{ $a['attribution_note'] ?? '' }}</p>
                </div>
            </div>
        </details>
    </x-tabler.page>
</x-operations.app>
