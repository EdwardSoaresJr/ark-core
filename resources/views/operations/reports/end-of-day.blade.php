<x-operations.app title="End of Day Report" ui="tabler">
    <x-tabler.page layout="fluid" title="End of Day" :breadcrumbs="[['label' => 'Reports', 'url' => route('operations.reports.index')]]" actions-label="Report shortcuts">
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.reports.index')" icon="arrow-left" label="All reports" />
        </x-slot:actions>

        @include('operations.reports.partials.end-of-day-report', [
            'eod' => $eod,
            'dateFormAction' => $dateFormAction,
        ])
    </x-tabler.page>
</x-operations.app>
