<x-operations.app title="Growth">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">ARK Growth</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Acquisition intelligence</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-500">
                    Growth is measured in completed work, not page views. Visitor → lead → conversation → appointment → repair order → invoice → revenue.
                </p>
            </div>
            @if ($dashboard['empty_state']['visible'] ?? false)
                <div class="border-t border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                    {{ $dashboard['empty_state']['message'] }}
                </div>
            @endif
            <div class="grid gap-px border-t border-slate-200 bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($dashboard['cards'] as $card)
                    <a href="{{ $card['url'] }}" class="block bg-white px-3 py-3 hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-bold text-slate-950">{{ $card['title'] }}</p>
                            <span @class([
                                'shrink-0 rounded-sm px-1.5 py-0.5 text-[11px] font-black tabular-nums',
                                'bg-emerald-100 text-emerald-900' => $card['score'] >= 70,
                                'bg-amber-100 text-amber-900' => $card['score'] >= 40 && $card['score'] < 70,
                                'bg-slate-100 text-slate-600' => $card['score'] < 40,
                            ])>{{ $card['score'] }}</span>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500">{{ $card['hint'] }}</p>
                        <p class="mt-2 text-xs leading-5 text-slate-700">{{ $card['summary'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-2">
            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Explainable health</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($dashboard['health'] as $domain => $score)
                        <li class="px-3 py-2.5">
                            <div class="flex items-baseline justify-between gap-2">
                                <p class="text-sm font-bold capitalize text-slate-950">{{ $domain }}</p>
                                <span class="text-sm font-black tabular-nums text-slate-800">{{ $score['score'] }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $score['summary'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Foundation stats</p>
                </div>
                <dl class="grid grid-cols-2 gap-px bg-slate-200">
                    @foreach ($dashboard['stats'] as $label => $value)
                        <div class="bg-white px-3 py-2">
                            <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ str_replace('_', ' ', $label) }}</dt>
                            <dd class="mt-1 text-lg font-black tabular-nums text-slate-950">
                                @if ($label === 'revenue_cents')
                                    ${{ number_format($value / 100, 0) }}
                                @else
                                    {{ number_format($value) }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Revenue heatmap</p>
                <p class="text-xs text-slate-500">{{ $heatmap['cols'] }}×{{ $heatmap['rows'] }} — tile color = closed-work revenue from the content registry.</p>
            </div>
            <div class="grid gap-0.5 p-2" style="grid-template-columns: repeat({{ $heatmap['cols'] }}, minmax(0, 1fr));">
                @foreach ($heatmap['cells'] as $cell)
                    <div
                        title="{{ $cell['title'] ?? 'Empty' }} · ${{ number_format($cell['revenue_cents'] / 100, 0) }}"
                        @class([
                            'aspect-square min-h-[10px] rounded-[1px]',
                            'bg-slate-100' => $cell['tier'] === 'empty',
                            'bg-slate-200' => $cell['tier'] === 'idle',
                            'bg-sky-200' => $cell['tier'] === 'trace',
                            'bg-sky-400' => $cell['tier'] === 'cool',
                            'bg-emerald-400' => $cell['tier'] === 'warm',
                            'bg-emerald-700' => $cell['tier'] === 'hot',
                        ])
                    ></div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('growth.revenue-explorer') }}" class="rounded-sm border border-sky-300 bg-sky-50 px-3 py-2 font-bold text-sky-950 hover:border-sky-400">Revenue Explorer</a>
            <a href="{{ route('growth.journey-explorer') }}" class="rounded-sm border border-sky-300 bg-sky-50 px-3 py-2 font-bold text-sky-950 hover:border-sky-400">Journey Explorer</a>
            <a href="{{ route('growth.sessions.index') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Landing sessions</a>
            <a href="{{ route('growth.content.index') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Content registry</a>
            <a href="{{ route('growth.audit') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">SEO audit</a>
            <a href="{{ route('growth.redirects.index') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Redirects</a>
        </div>
    </section>
</x-operations.app>
