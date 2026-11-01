@php
    use App\Ark\Operations\Reports\OperationalReportDateScope;

    $fromDate = OperationalReportDateScope::shopDateString($from);
    $toDate = OperationalReportDateScope::shopDateString($to);
@endphp

<x-operations.app title="Operational Report" ui="tabler">
    <x-tabler.page layout="fluid" title="Executive Pulse" :pretitle="'Reports · '.$activeTab->label()" actions-label="Report shortcuts">
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.reports.index')" icon="arrow-left" label="All reports" />
        </x-slot:actions>

        <section class="card mb-3" aria-label="Report range" data-report-range>
            <div class="card-body p-2 p-md-3">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg">
                        <p class="text-secondary small mb-0">
                            {{ OperationalReportDateScope::shopRangeLabel($from, $to) }}.
                            @if ($fromDate === $toDate)
                                @php
                                    $eodUrl = \App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess::allows(auth()->user())
                                        ? route('operations.owner.day-review', ['date' => $fromDate])
                                        : route('operations.reports.end-of-day', ['date' => $fromDate]);
                                @endphp
                                <a href="{{ $eodUrl }}">End of Day card</a>
                                for this shop day.
                            @endif
                            <strong>Posted invoice sales</strong> uses the posted date and the frozen invoice.
                            <strong>Cash collected</strong> uses payment and deposit dates. Write-offs are separate.
                        </p>
                    </div>
                    <div class="col-12 col-lg-auto">
                        <form method="GET" action="{{ route('operations.reports.operational') }}" class="row g-2 align-items-end">
                            <input type="hidden" name="tab" value="{{ $activeTab->value }}">
                            <div class="col-6 col-sm-auto">
                                <label class="form-label" for="from">From</label>
                                <input id="from" name="from" type="date" value="{{ $fromDate }}" min="{{ OperationalReportDateScope::shopDateString($trustworthyStartsAt) }}" class="form-control">
                            </div>
                            <div class="col-6 col-sm-auto">
                                <label class="form-label" for="to">To</label>
                                <input id="to" name="to" type="date" value="{{ $toDate }}" class="form-control">
                            </div>
                            <div class="col-12 col-sm-auto">
                                <button type="submit" class="btn btn-primary w-100">Run</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <div class="row row-cards mb-3" role="list" aria-label="Report KPIs">
            @foreach ($kpis as $kpi)
                <div class="col-6 col-sm-4 col-lg-3 col-xl-2" role="listitem">
                    <div class="card card-sm h-100" data-report-kpi>
                        <div class="card-body">
                            <div class="subheader">{{ $kpi['label'] }}</div>
                            <div class="h3 mb-0 mt-1 ark-money">{{ $kpi['value'] }}</div>
                            <div @class([
                                'small mt-1',
                                'text-green' => ($kpi['tone'] ?? null) === 'good',
                                'text-warning' => ($kpi['tone'] ?? null) === 'warn',
                                'text-secondary' => ! in_array($kpi['tone'] ?? null, ['good', 'warn'], true),
                            ])>{{ $kpi['hint'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @include('operations.reports.partials.report-tabs')
    </x-tabler.page>
</x-operations.app>
