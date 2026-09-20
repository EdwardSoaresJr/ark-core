<x-operations.app title="Operational Report">
    <section class="space-y-3">
        <div class="flex flex-wrap items-center gap-2 px-0.5 text-xs">
            <a href="{{ route('operations.reports.index') }}" class="font-bold text-sky-800 underline decoration-sky-200 hover:text-sky-950">← All reports</a>
            <span class="text-slate-300">·</span>
            <span class="font-semibold text-slate-600">{{ $activeTab->label() }}</span>
        </div>

        <div class="border border-slate-300 bg-white">
            <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Executive Pulse</p>
                    <p class="mt-0.5 text-xs font-medium text-slate-500">
                        {{ App\Ark\Operations\Reports\OperationalReportDateScope::shopRangeLabel($from, $to) }}.
                        @if (App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($from) === App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($to))
                            @php
                                $shopDay = App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($from);
                                $eodUrl = \App\Ark\Operations\ShopExcellence\OwnerWorkspaceAccess::allows(auth()->user())
                                    ? route('operations.owner.day-review', ['date' => $shopDay])
                                    : route('operations.reports.end-of-day', ['date' => $shopDay]);
                            @endphp
                            <a href="{{ $eodUrl }}" class="font-bold text-sky-800 underline decoration-sky-200 hover:text-sky-950">End of Day card</a>
                            for this shop day.
                        @endif
                        <strong class="font-semibold text-slate-600">Sales Posted</strong> uses posted date.
                        <strong class="font-semibold text-slate-600">Cash Collected</strong> uses payment/deposit dates.
                    </p>
                </div>

                <form method="GET" action="{{ route('operations.reports.operational') }}" class="ops-on-dark ops-report-range grid gap-1.5 text-xs sm:grid-cols-[9rem_9rem_auto]">
                    <input type="hidden" name="tab" value="{{ $activeTab->value }}">
                    <x-operations.date-field name="from" :value="App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($from)" :min="App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($trustworthyStartsAt)" />
                    <x-operations.date-field name="to" :value="App\Ark\Operations\Reports\OperationalReportDateScope::shopDateString($to)" />
                    <button type="submit" class="min-h-9 rounded-sm bg-white px-3 font-bold text-slate-950 hover:bg-slate-100">Run</button>
                </form>
            </div>

            <div class="grid grid-cols-2 gap-px bg-slate-200 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                @foreach ($kpis as $kpi)
                    <div class="bg-white px-3 py-2">
                        <p class="text-[10px] font-bold uppercase leading-tight tracking-[0.08em] text-slate-500">{{ $kpi['label'] }}</p>
                        <p class="mt-1 text-lg font-black tabular-nums leading-5 text-slate-950">{{ $kpi['value'] }}</p>
                        <p @class([
                            'mt-0.5 text-[11px] font-medium leading-snug',
                            'text-emerald-700' => ($kpi['tone'] ?? null) === 'good',
                            'text-amber-700' => ($kpi['tone'] ?? null) === 'warn',
                            'text-slate-400' => ! in_array($kpi['tone'] ?? null, ['good', 'warn'], true),
                        ])>{{ $kpi['hint'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        @include('operations.reports.partials.report-tabs')
    </section>
</x-operations.app>
