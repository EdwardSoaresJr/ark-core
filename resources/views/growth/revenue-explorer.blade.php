<x-operations.app title="Revenue Explorer">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Revenue Explorer</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Decision engine for shop owners</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-500">
                    Not page analytics — answers tied to closed repair orders and attributed acquisition.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 border-b border-slate-200 px-3 py-2">
                @foreach ($catalog as $item)
                    <a
                        href="{{ route('growth.revenue-explorer', array_filter(['q' => $item['key'], 'threshold' => $queryType === 'high_revenue_pages' ? $threshold : null])) }}"
                        @class([
                            'rounded-sm border px-2.5 py-1.5 text-xs font-bold',
                            'border-sky-400 bg-sky-50 text-sky-950' => $queryType === $item['key'],
                            'border-slate-300 bg-white text-slate-700 hover:border-slate-400' => $queryType !== $item['key'],
                        ])
                    >{{ $item['label'] }}</a>
                @endforeach
            </div>
            <div class="px-3 py-3">
                <p class="text-sm font-bold text-slate-950">{{ $result['question'] }}</p>
                @if ($queryType === 'high_revenue_pages')
                    <form method="GET" action="{{ route('growth.revenue-explorer') }}" class="mt-2 flex flex-wrap items-end gap-2">
                        <input type="hidden" name="q" value="high_revenue_pages">
                        <label class="text-xs text-slate-600">
                            Minimum revenue ($)
                            <input type="number" name="threshold" value="{{ (int) ($threshold / 100) }}" min="0" step="1000" class="mt-0.5 block w-32 rounded-sm border border-slate-300 px-2 py-1 text-sm">
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
                            <td class="px-3 py-4 text-slate-500" colspan="6">No rows yet for this question.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <a href="{{ route('growth.opportunities.index') }}" class="inline-block text-xs font-bold text-sky-800 hover:text-sky-950">← Opportunities</a>
    </section>
</x-operations.app>
