<x-operations.app title="Entity Health">
    <section class="space-y-4">
        @include('growth.partials.subnav')

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Market operations</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Entity Health</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-600">
                    Observe public identity, do not manage it. One source of truth in Settings; audiences verified by hand; inconsistencies shown as actionable observations — not scores.
                </p>
            </div>
        </div>

        @if (session('status'))
            <p class="rounded-sm border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900">{{ session('status') }}</p>
        @endif

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <h2 class="text-sm font-black text-slate-950">Notebook</h2>
            </div>
            <div class="px-3 py-3 text-sm leading-relaxed text-slate-700">
                <p class="font-semibold text-slate-900">{{ $entityHealth['notebook']['summary'] }}</p>
                @if ($entityHealth['notebook']['bullets'] !== [])
                    <ul class="mt-2 list-none space-y-1 pl-0">
                        @foreach ($entityHealth['notebook']['bullets'] as $bullet)
                            <li class="text-slate-700">• {{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-3 text-xs text-slate-500">
                    Last reviewed: {{ $entityHealth['notebook']['last_reviewed_at'] }}
                </p>
            </div>
        </div>

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <h2 class="text-sm font-black text-slate-950">Canonical Identity</h2>
                <p class="mt-0.5 text-xs text-slate-600">Authority — edit in Settings → Shop → General.</p>
            </div>
            <dl class="divide-y divide-slate-100">
                @foreach ($entityHealth['canonical'] as $field)
                    <div class="flex flex-wrap items-start justify-between gap-3 px-3 py-3">
                        <div class="min-w-0">
                            <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $field['label'] }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm font-semibold text-slate-900">{{ $field['value'] }}</dd>
                        </div>
                        @if ($field['complete'])
                            <span class="rounded-sm bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-emerald-900">✓</span>
                        @else
                            <span class="rounded-sm bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-900">Incomplete</span>
                        @endif
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <h2 class="text-sm font-black text-slate-950">Audiences</h2>
                <p class="mt-0.5 text-xs text-slate-600">Mark verified when you have checked them manually.</p>
            </div>
            <ul class="divide-y divide-slate-100">
                @foreach ($entityHealth['audiences'] as $surface)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-3 py-3">
                        <div>
                            <p class="text-sm font-bold text-slate-950">{{ $surface['label'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-600">
                                Last verified
                                @if ($surface['last_verified_at'])
                                    {{ \Illuminate\Support\Carbon::parse($surface['last_verified_at'])->format('F j, Y') }}
                                    @if ($surface['is_stale'])
                                        <span class="font-semibold text-amber-800">· {{ $surface['days_since_verified'] }} days old</span>
                                    @else
                                        <span class="font-semibold text-emerald-800">· ✓</span>
                                    @endif
                                @else
                                    <span class="font-semibold text-amber-800">· Never verified</span>
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('growth.entity-health.verify', $surface['key']) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-50">
                                Verified today
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <h2 class="text-sm font-black text-slate-950">Identity Observations</h2>
                <p class="mt-0.5 text-xs text-slate-600">Canonical authority compared to ARK projections — no external APIs.</p>
            </div>
            @if ($entityHealth['identity_observations'] === [])
                <p class="px-3 py-4 text-sm text-slate-600">No identity observations.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($entityHealth['identity_observations'] as $finding)
                        <li class="px-3 py-3">
                            <p class="text-sm font-bold text-slate-950">{{ $finding['title'] }}</p>
                            <dl class="mt-2 grid gap-2 text-xs text-slate-700 sm:grid-cols-2">
                                <div class="rounded-sm border border-slate-200 bg-slate-50 px-2.5 py-2">
                                    <dt class="font-bold uppercase tracking-[0.06em] text-slate-500">Canonical</dt>
                                    <dd class="mt-1 font-semibold text-slate-500">{{ $finding['canonical_label'] }}</dd>
                                    <dd class="mt-0.5 whitespace-pre-line">{{ $finding['canonical_value'] }}</dd>
                                </div>
                                <div class="rounded-sm border border-slate-200 bg-slate-50 px-2.5 py-2">
                                    <dt class="font-bold uppercase tracking-[0.06em] text-slate-500">Projection</dt>
                                    <dd class="mt-1 font-semibold text-slate-500">{{ $finding['projection_label'] }}</dd>
                                    <dd class="mt-0.5 whitespace-pre-line">{{ $finding['projection_value'] }}</dd>
                                </div>
                            </dl>
                            <p class="mt-2 text-xs font-bold text-slate-900">Recommended action</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-700">{{ $finding['recommended_action'] }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <p class="text-xs text-slate-500">
            On-page SEO checks live separately on
            <a href="{{ route('growth.audit') }}" class="font-bold text-sky-800 hover:text-sky-950">SEO Audit</a>.
            Entity Health does not manage citations, sync Google, or produce rankings.
        </p>

        <a href="{{ route('growth.opportunities.index') }}" class="inline-block text-xs font-bold text-sky-800 hover:text-sky-950">← Opportunities</a>
    </section>
</x-operations.app>
