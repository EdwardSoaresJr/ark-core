@php
    use App\Ark\Operations\Reports\OperationalReportDateScope;
    use App\Ark\Operations\Reports\OperationalReportTab;

    $reportQuery = [
        'from' => OperationalReportDateScope::shopDateString($from),
        'to' => OperationalReportDateScope::shopDateString($to),
    ];
@endphp

<div class="overflow-hidden border border-slate-300 bg-white">
    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $activeTab->label() }}</p>
                <p class="text-xs text-slate-400">{{ $activeTab->description() }}</p>
            </div>

            <nav class="ops-report-tabs" aria-label="Report sections">
                @foreach (OperationalReportTab::casesInDisplayOrder() as $tab)
                    <a
                        href="{{ route('operations.reports.operational', array_merge($reportQuery, ['tab' => $tab->value])) }}"
                        @class([
                            'ops-report-tab',
                            'ops-report-tab--active' => $activeTab === $tab,
                        ])
                        @if ($activeTab === $tab) aria-current="page" @endif
                    >
                        {{ $tab->label() }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>

    <div class="p-3">
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
</div>
