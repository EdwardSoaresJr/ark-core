<x-operations.app title="Business">
    <section class="ops-business space-y-3">
        <header class="border border-slate-300 bg-white px-4 py-4">
            <p class="text-xl font-black text-slate-950">{{ $business->greeting }}</p>
            <p class="mt-2 text-sm font-semibold text-slate-900">Business — market, growth, and yesterday.</p>
            <p class="mt-1 text-sm text-slate-600">Operational work lives on Today. This cockpit is for shop health.</p>
        </header>

        @foreach ($business->nonEmptySections() as $section)
            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $section->title }}</p>
                </div>
                @if ($section->key === 'market_pressure' && filled($section->panel))
                    @include('operations.today.partials.market-pressure-panel', ['panel' => $section->panel])
                @endif
                @if ($section->actions !== [])
                    <ul class="divide-y divide-slate-100">
                        @foreach ($section->actions as $action)
                            <li>
                                <a
                                    href="{{ $action->url }}"
                                    class="group block px-3 py-3 no-underline transition hover:bg-slate-50"
                                >
                                    <p class="text-sm font-bold text-slate-950 group-hover:text-[#0099cc]">{{ $action->title }}</p>
                                    @if (filled($action->reason))
                                        <p class="mt-1 text-xs text-slate-600">{{ $action->reason }}</p>
                                    @endif
                                    @if (filled($action->expectedOutcome))
                                        <p class="mt-1 text-xs text-slate-500">{{ $action->expectedOutcome }}</p>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Yesterday</p>
            </div>
            <dl class="divide-y divide-slate-100">
                @forelse ($business->yesterdaySummary as $row)
                    <div class="grid grid-cols-[9rem_1fr] gap-2 px-3 py-2.5 text-sm">
                        <dt class="font-bold text-slate-500">{{ $row['label'] }}</dt>
                        <dd>
                            <p class="font-black tabular-nums text-slate-950">{{ $row['value'] }}</p>
                            @if (! empty($row['hint']))
                                <p class="mt-0.5 text-xs text-slate-500">{{ $row['hint'] }}</p>
                            @endif
                        </dd>
                    </div>
                @empty
                    <div class="px-3 py-4 text-sm text-slate-500">No posted activity recorded for yesterday.</div>
                @endforelse
            </dl>
        </div>

        @if ($business->links !== [])
            <div class="flex flex-wrap gap-2 text-xs">
                @foreach ($business->links as $link)
                    <a
                        href="{{ $link['url'] }}"
                        class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400"
                    >{{ $link['label'] }}</a>
                @endforeach
            </div>
        @endif
    </section>
</x-operations.app>
