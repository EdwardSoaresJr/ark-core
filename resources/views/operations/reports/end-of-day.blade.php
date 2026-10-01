<x-operations.app title="End of Day Report">
    <section class="ops-reports-eod space-y-3">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <a href="{{ route('operations.reports.index') }}" class="font-bold text-sky-800 underline decoration-sky-200 hover:text-sky-950">← All reports</a>
        </div>

        @include('operations.reports.partials.end-of-day-report', [
            'eod' => $eod,
            'dateFormAction' => $dateFormAction,
        ])
    </section>
</x-operations.app>
