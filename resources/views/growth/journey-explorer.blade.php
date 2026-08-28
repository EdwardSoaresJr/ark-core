<x-operations.app title="Journey Explorer">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Journey Explorer</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">How did this repair come into existence?</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-500">
                    Revenue Explorer's sibling — query full acquisition-to-repair journeys ARK owns end to end.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 border-b border-slate-200 px-3 py-2">
                @foreach ($catalog as $item)
                    <a
                        href="{{ route('growth.journey-explorer', array_filter([
                            'q' => $item['key'],
                            'threshold' => $queryType === 'high_revenue_repairs' ? (int) ($threshold / 100) : null,
                            'keyword' => in_array($queryType, ['common_path_by_landing', 'first_touch_by_concern'], true) ? $keyword : null,
                            'min_views' => $queryType === 'estimate_views_before_approval' ? $minViews : null,
                        ])) }}"
                        @class([
                            'rounded-sm border px-2.5 py-1.5 text-xs font-bold',
                            'border-sky-400 bg-sky-50 text-sky-950' => $queryType === $item['key'],
                            'border-slate-300 bg-white text-slate-700 hover:border-slate-400' => $queryType !== $item['key'],
                        ])
                        title="{{ $item['description'] }}"
                    >{{ $item['label'] }}</a>
                @endforeach
            </div>
            <div class="px-3 py-3">
                <p class="text-sm font-bold text-slate-950">{{ $result['question'] }}</p>

                @if ($queryType === 'high_revenue_repairs')
                    <form method="GET" action="{{ route('growth.journey-explorer') }}" class="mt-2 flex flex-wrap items-end gap-2">
                        <input type="hidden" name="q" value="high_revenue_repairs">
                        <label class="text-xs text-slate-600">
                            Minimum revenue ($)
                            <input type="number" name="threshold" value="{{ (int) ($threshold / 100) }}" min="0" step="500" class="mt-0.5 block w-32 rounded-sm border border-slate-300 px-2 py-1 text-sm">
                        </label>
                        <button type="submit" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800">Apply</button>
                    </form>
                @endif

                @if (in_array($queryType, ['common_path_by_landing', 'first_touch_by_concern'], true))
                    <form method="GET" action="{{ route('growth.journey-explorer') }}" class="mt-2 flex flex-wrap items-end gap-2">
                        <input type="hidden" name="q" value="{{ $queryType }}">
                        <label class="text-xs text-slate-600">
                            Keyword
                            <input type="text" name="keyword" value="{{ $keyword }}" class="mt-0.5 block w-48 rounded-sm border border-slate-300 px-2 py-1 text-sm">
                        </label>
                        <button type="submit" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800">Apply</button>
                    </form>
                @endif

                @if ($queryType === 'estimate_views_before_approval')
                    <form method="GET" action="{{ route('growth.journey-explorer') }}" class="mt-2 flex flex-wrap items-end gap-2">
                        <input type="hidden" name="q" value="estimate_views_before_approval">
                        <label class="text-xs text-slate-600">
                            Minimum estimate views
                            <input type="number" name="min_views" value="{{ $minViews }}" min="2" class="mt-0.5 block w-24 rounded-sm border border-slate-300 px-2 py-1 text-sm">
                        </label>
                        <button type="submit" class="rounded-sm border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-800">Apply</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($result['empty_hint'] ?? null)
            <div class="border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                {{ $result['empty_hint'] }}
            </div>
        @endif

        <div class="overflow-x-auto border border-slate-300 bg-white">
            <table class="min-w-full text-left text-xs">
                <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">
                    <tr>
                        @if ($result['rows'] !== [])
                            @foreach (array_keys($result['rows'][0]) as $column)
                                <th class="px-3 py-2">{{ str_replace('_', ' ', $column) }}</th>
                            @endforeach
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($result['rows'] as $row)
                        <tr>
                            @foreach ($row as $value)
                                <td class="px-3 py-2 tabular-nums text-slate-800">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-4 text-slate-500">No matching journeys yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-operations.app>
