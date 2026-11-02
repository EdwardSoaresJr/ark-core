@php
    use App\Ark\Operations\Reports\OperationalReportDateScope;
    use App\Ark\Operations\Reports\OperationalReportTab;

    $reportQuery = [
        'from' => OperationalReportDateScope::shopDateString($from),
        'to' => OperationalReportDateScope::shopDateString($to),
    ];
@endphp

<nav class="nav nav-pills mb-3" aria-label="Report sections" data-report-tabs>
    @foreach (OperationalReportTab::casesInDisplayOrder() as $tab)
        <a
            href="{{ route('operations.reports.operational', array_merge($reportQuery, ['tab' => $tab->value])) }}"
            @class(['nav-link', 'active' => $activeTab === $tab])
            @if ($activeTab === $tab) aria-current="page" @endif
        >
            {{ $tab->label() }}
        </a>
    @endforeach
</nav>

<div class="mb-3">
    <h2 class="mb-0">{{ $activeTab->label() }}</h2>
    <div class="text-secondary">{{ $activeTab->description() }}</div>
</div>

<div data-report-tab="{{ $activeTab->value }}">
    @if ($activeTab === OperationalReportTab::Operations)
        @include('operations.reports.partials.operational-intelligence')
    @elseif ($activeTab === OperationalReportTab::MarginHealth)
        @include('operations.reports.partials.margin-health-tab')
    @elseif ($activeTab === OperationalReportTab::OwnerPl)
        @include('operations.reports.partials.owner-pl-tab')
    @elseif ($activeTab === OperationalReportTab::Financial)
        @include('operations.reports.partials.financial-tab')
    @else
        @include('operations.reports.partials.production-tab')
    @endif
</div>
