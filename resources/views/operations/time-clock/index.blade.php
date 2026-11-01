@php
    use App\Ark\Operations\Labor\TechnicianTimeClockProjection;
    use App\Ark\Operations\Settings\ShopDisplayTimezone;

    $p = $projection;
    $showStaffList = ($canManage || $canPunchForStaff) && $staffTechnicians->isNotEmpty();
@endphp

<x-operations.app title="Time Clock" ui="tabler">
    <x-tabler.page layout="focused" pretitle="Time clock" :title="$p['technician']->name ?? 'Staff punches'">
        <div class="d-flex flex-column gap-3" data-time-clock>
            @if (session('status'))
                <div class="alert alert-success mb-0" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->has('time_clock'))
                <div class="alert alert-danger mb-0" role="alert">{{ $errors->first('time_clock') }}</div>
            @endif

            @unless ($p)
                <p class="text-secondary mb-0">Punch staff in and out when work starts and ends. Hours feed compensable time - not a paycheck.</p>
            @endunless

            @if (($canManage || $canPunchForStaff) && $needsResolution->isNotEmpty())
                <div class="alert alert-warning mb-0" role="alert" data-time-clock-needs-resolution>
                    <h4 class="alert-title">Needs resolution</h4>
                    <div class="text-secondary">Open punches crossed shop midnight. Clock out or ask an admin to correct before totals are trusted.</div>
                    <ul class="mb-0 mt-2">
                        @foreach ($needsResolution as $session)
                            <li>
                                <a href="{{ route('operations.time-clock.staff', $session->technician ?? $session->user_id) }}" class="fw-bold">
                                    {{ $session->technician?->name ?? 'Staff' }}
                                </a>
                                · in {{ ShopDisplayTimezone::format($session->clocked_in_at) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($p)
                @php
                    $elapsedHours = number_format($p['today_compensable_hours'], 2);
                    $elapsedLabel = sprintf('%d:%02d', intdiv($p['today_elapsed_seconds'], 3600), intdiv($p['today_elapsed_seconds'] % 3600, 60));
                @endphp
                <div class="card" data-time-clock-self>
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-sm-6 text-center">
                                <div class="subheader">Today elapsed</div>
                                <div class="display-5 fw-bold" style="font-variant-numeric: tabular-nums">{{ $elapsedLabel }}</div>
                                <div class="small text-secondary">{{ $elapsedHours }} compensable hr</div>
                                <div class="fw-medium mt-2" data-time-clock-status>{{ $p['status_label'] }}</div>
                            </div>
                            <div class="col-sm-6 d-flex flex-column gap-2">
                                @if ($p['is_clocked_in'])
                                    <form method="POST" action="{{ route('operations.time-clock.out') }}">
                                        @csrf
                                        <input type="hidden" name="lunch" value="1" />
                                        <button type="submit" class="btn btn-warning w-100">Out for Lunch</button>
                                    </form>
                                    <form method="POST" action="{{ route('operations.time-clock.out') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-lg w-100">Clock Out</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('operations.time-clock.in') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-lg w-100">
                                            {{ $p['awaiting_lunch_return'] ? 'Back from Lunch' : 'Clock In' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" data-time-clock-today>
                    <div class="card-header">
                        <h3 class="card-title">Today punches</h3>
                    </div>
                    @if ($p['today_punches']->isEmpty())
                        <div class="card-body text-secondary">No punches yet today.</div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($p['today_punches'] as $session)
                                <div class="list-group-item">
                                    <div class="fw-medium" style="font-variant-numeric: tabular-nums">
                                        {{ ShopDisplayTimezone::format($session->clocked_in_at, 'g:i A') }}
                                        →
                                        {{ $session->clocked_out_at ? ShopDisplayTimezone::format($session->clocked_out_at, 'g:i A') : 'open' }}
                                    </div>
                                    <div class="small text-secondary">
                                        {{ $session->statusEnum()->label() }}
                                        @if ($session->closeReasonEnum())
                                            · {{ $session->closeReasonEnum()->label() }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            @if ($showStaffList)
                <div class="card" data-time-clock-staff>
                    <div class="card-header">
                        <h3 class="card-title">Staff punches</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($staffTechnicians as $technician)
                            @php
                                $staffProjection = TechnicianTimeClockProjection::forTechnician($technician);
                            @endphp
                            <div class="list-group-item">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div style="min-width: 0">
                                        <div class="fw-medium">{{ $technician->name }}</div>
                                        <div class="small text-secondary">{{ $staffProjection['status_label'] }}</div>
                                    </div>
                                    <div class="btn-list">
                                        @if ($canPunchForStaff)
                                            @if ($staffProjection['is_clocked_in'])
                                                <form method="POST" action="{{ route('operations.time-clock.staff.out', $technician) }}">
                                                    @csrf
                                                    <input type="hidden" name="lunch" value="1" />
                                                    <button type="submit" class="btn btn-sm btn-warning">Out for Lunch</button>
                                                </form>
                                                <form method="POST" action="{{ route('operations.time-clock.staff.out', $technician) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary">Clock Out</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('operations.time-clock.staff.in', $technician) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        {{ $staffProjection['awaiting_lunch_return'] ? 'Back from Lunch' : 'Clock In' }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                        <a href="{{ route('operations.time-clock.staff', $technician) }}" class="btn btn-sm btn-ghost-secondary">History</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
