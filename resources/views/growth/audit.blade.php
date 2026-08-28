<x-operations.app title="SEO Audit">
    <section class="space-y-4">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">SEO audit</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Explainable recommendations</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-600">
                    Structural checks read from configuration and public surface authorities — instant after deploy.
                    Runtime checks wait for a nightly crawl. Search performance lives on Opportunities (Search Console).
                </p>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-sm bg-red-100 px-2 py-0.5 font-bold text-red-900">{{ $audit['counts']['critical'] ?? 0 }} critical</span>
                    <span class="rounded-sm bg-amber-100 px-2 py-0.5 font-bold text-amber-900">{{ $audit['counts']['warning'] ?? 0 }} warning</span>
                    <span class="rounded-sm bg-emerald-100 px-2 py-0.5 font-bold text-emerald-900">{{ $audit['counts']['passed'] ?? 0 }} verified</span>
                </div>
            </div>
        </div>

        @foreach ($audit['channels'] ?? [] as $channel)
            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-black text-slate-950">{{ $channel['label'] }} audit</h2>
                        <span class="text-[11px] font-bold text-slate-500">
                            {{ $channel['passed_count'] }} passed · {{ $channel['failed_count'] }} need attention
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-600">{{ $channel['description'] }}</p>
                </div>

                <ul class="divide-y divide-slate-100">
                    @forelse ($channel['findings'] as $finding)
                        <li class="px-3 py-3" x-data="{ open: false }">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <button
                                    type="button"
                                    class="text-left text-sm font-bold text-slate-950 hover:text-sky-900"
                                    @click="open = !open"
                                >
                                    {{ $finding['title'] ?? $finding['message'] }}
                                </button>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($finding['passed'] ?? false)
                                        <span class="rounded-sm bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-900">Pass</span>
                                    @else
                                        <span @class([
                                            'rounded-sm px-1.5 py-0.5 text-[10px] font-bold uppercase',
                                            'bg-red-100 text-red-900' => in_array($finding['severity'], ['critical', 'high'], true),
                                            'bg-amber-100 text-amber-900' => in_array($finding['severity'], ['warning', 'medium'], true),
                                            'bg-slate-100 text-slate-600' => ! in_array($finding['severity'], ['critical', 'high', 'warning', 'medium'], true),
                                        ])>{{ $finding['severity'] }}</span>
                                    @endif
                                    <span class="rounded-sm bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">
                                        {{ $finding['authority_source_label'] ?? 'Unknown source' }}
                                    </span>
                                </div>
                            </div>

                            @if (! ($finding['passed'] ?? false))
                                <p class="mt-1 text-xs text-slate-700">{{ $finding['message'] }}</p>
                            @endif

                            @if ($finding['path'] ?? null)
                                <p class="mt-0.5 font-mono text-[11px] text-slate-500">{{ $finding['path'] }}</p>
                            @endif

                            <div x-show="open" x-cloak class="mt-2 rounded-sm border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs text-slate-700">
                                <p class="font-bold text-slate-900">Why ARK knows</p>
                                @if (filled($finding['evidence'] ?? null))
                                    <p class="mt-1">{{ $finding['evidence'] }}</p>
                                @endif
                                <p class="mt-1 text-slate-600">
                                    Authority: {{ $finding['authority_source_label'] ?? '—' }}.
                                    @if ($finding['heals_on_deploy'] ?? false)
                                        Updates on deploy — no crawl required.
                                    @else
                                        Requires fresh runtime observation.
                                    @endif
                                </p>
                                @if (filled($finding['recommendation'] ?? null) && ! ($finding['passed'] ?? false))
                                    <p class="mt-2 font-bold text-slate-900">Recommendation</p>
                                    <p class="mt-0.5">{{ $finding['recommendation'] }}</p>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-3 py-4 text-sm text-slate-500">No findings in this channel.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach

        <p class="text-xs text-slate-500">
            Search clicks, impressions, and indexing coverage are tracked separately on
            <a href="{{ route('growth.opportunities.index') }}" class="font-bold text-sky-800 hover:text-sky-950">Opportunities</a>
            — they wait on Google, not ARK configuration.
        </p>

        <a href="{{ route('growth.opportunities.index') }}" class="inline-block text-xs font-bold text-sky-800 hover:text-sky-950">← Opportunities</a>
    </section>
</x-operations.app>
