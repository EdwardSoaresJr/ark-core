<x-operations.app title="Technician production" ui="tabler">
    <x-tabler.page layout="fluid" title="Technician production" pretitle="Owner · Production assist" :subtitle="$northStar">
        <p class="small text-secondary mb-3">Base compensation assist is not a paycheck. Overtime and payroll adjustments are not included. Recognition history begins {{ $recognitionStartsAt }}.</p>

        <div class="card mb-3">
            <div class="card-body p-2 p-md-3">
                <form method="GET" action="{{ route('operations.owner.technician-production.index') }}" class="row g-2 align-items-end" data-technician-production-range>
                    <div class="col-6 col-sm-auto">
                        <label class="form-label" for="from">From</label>
                        <input id="from" name="from" type="date" value="{{ $from }}" class="form-control">
                    </div>
                    <div class="col-6 col-sm-auto">
                        <label class="form-label" for="to">To</label>
                        <input id="to" name="to" type="date" value="{{ $to }}" class="form-control">
                    </div>
                    <div class="col-12 col-sm-auto">
                        <button type="submit" class="btn btn-primary w-100">Show week</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" data-technician-production-table>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th scope="col">Technician</th>
                            <th scope="col" class="text-end">Clock</th>
                            <th scope="col" class="text-end">Recognized</th>
                            <th scope="col" class="text-end">Pending</th>
                            <th scope="col" class="text-end">Rec. / clock</th>
                            <th scope="col" class="text-end">Floor exposure</th>
                            <th scope="col"><span class="visually-hidden">Open</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr data-technician-production-row="{{ $row['technician_id'] }}">
                                <td class="fw-medium">
                                    {{ $row['technician_name'] }}
                                    @if ($row['overtime_review_required'])
                                        <span class="badge bg-yellow-lt ms-1">OT review</span>
                                    @endif
                                    @if ($row['history_unavailable'])
                                        <span class="badge bg-secondary-lt ms-1">History N/A</span>
                                    @endif
                                </td>
                                <td class="text-end ark-money">{{ number_format((float) $row['clock_hours'], 1) }}</td>
                                <td class="text-end ark-money">
                                    @if ($row['history_unavailable'])
                                        -
                                    @else
                                        {{ number_format((float) $row['recognized_flag_hours'], 1) }}
                                    @endif
                                </td>
                                <td class="text-end ark-money">
                                    @if ($row['history_unavailable'])
                                        -
                                    @else
                                        {{ number_format((float) $row['pending_flag_hours'], 1) }}
                                    @endif
                                </td>
                                <td class="text-end ark-money">
                                    @if ($row['recognized_efficiency_percent'] === null)
                                        -
                                    @else
                                        {{ number_format((float) $row['recognized_efficiency_percent'], 1) }}%
                                    @endif
                                </td>
                                <td class="text-end fw-medium ark-money">
                                    @if ($row['history_unavailable'] || $row['floor_exposure_cents'] === null)
                                        -
                                    @else
                                        ${{ number_format($row['floor_exposure_cents'] / 100, 2) }}
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('operations.owner.technician-production.show', ['user' => $row['technician_id'], 'from' => $from, 'to' => $to]) }}" class="btn btn-sm">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-secondary">No Flag technicians for this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-tabler.page>
</x-operations.app>
