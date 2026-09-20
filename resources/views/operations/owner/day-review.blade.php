<x-operations.app title="Day Review">
    <section class="ops-day-review space-y-3">
        @if ($targetReviewStale)
            <div class="border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                @if ($lastTargetReview)
                    Last target review: {{ $lastTargetReview }} — overdue for quarterly refresh.
                @else
                    Shop excellence targets have not been reviewed yet.
                @endif
                <a href="{{ route('operations.settings.shop.edit', ['section' => 'excellence']) }}" class="ml-1 font-bold underline">Update owner targets</a>
            </div>
        @endif

        @include('operations.reports.partials.end-of-day-report', ['eod' => $eod])

        <div class="ops-day-review-queue border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Tomorrow's queue pressure</p>
                <p class="text-xs text-slate-500">name the first move before you leave. Numbers above; unfinished work below.</p>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($priorities as $priority)
                    <li class="flex flex-wrap items-baseline justify-between gap-2 px-3 py-2.5">
                        <div>
                            <p class="text-sm font-bold text-slate-950">{{ $priority['label'] }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $priority['hint'] }}</p>
                        </div>
                        <span @class([
                            'shrink-0 rounded-sm px-2 py-0.5 text-xs font-black tabular-nums',
                            'bg-amber-100 text-amber-900' => $priority['tone'] !== 'calm',
                            'bg-slate-100 text-slate-600' => $priority['tone'] === 'calm',
                        ])>{{ $priority['count'] }}</span>
                    </li>
                @empty
                    <li class="px-3 py-4 text-sm text-slate-500">No queue pressure flagged — shop flow is clear.</li>
                @endforelse
            </ul>
        </div>

        @if (($nudgeInsight['acted'] ?? 0) + ($nudgeInsight['dismissed'] ?? 0) > 0)
            <details class="border border-slate-300 bg-white">
                <summary class="cursor-pointer list-none px-3 py-2 text-xs font-bold text-slate-700 marker:content-none [&::-webkit-details-marker]:hidden">
                    Comms nudge measurement · 7 days
                </summary>
                <div class="grid grid-cols-3 gap-px border-t border-slate-200 bg-slate-200">
                    <div class="bg-white px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Acted</p>
                        <p class="mt-1 text-lg font-black tabular-nums text-emerald-800">{{ $nudgeInsight['acted'] }}</p>
                    </div>
                    <div class="bg-white px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Dismissed</p>
                        <p class="mt-1 text-lg font-black tabular-nums text-slate-700">{{ $nudgeInsight['dismissed'] }}</p>
                    </div>
                    <div class="bg-white px-3 py-2">
                        <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">Action rate</p>
                        <p class="mt-1 text-lg font-black tabular-nums text-slate-950">{{ $nudgeInsight['action_rate'] !== null ? $nudgeInsight['action_rate'].'%' : '—' }}</p>
                    </div>
                </div>
            </details>
        @endif

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('operations.reports.index') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">All reports</a>
            <a href="{{ $eod->reportUrl }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Operational Report</a>
            <a href="{{ route('operations.workboard') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Workboard</a>
            <a href="{{ route('operations.learn.show', ['role' => 'owner', 'article' => 'bookend-walkthrough']) }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Day Review guide</a>
        </div>
    </section>
</x-operations.app>
