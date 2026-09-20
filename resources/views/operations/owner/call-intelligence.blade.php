<x-operations.app title="Call & SMS Intelligence">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Owner</p>
                <h1 class="mt-0.5 text-base font-black text-slate-950">Call & SMS intelligence</h1>
                <p class="mt-1 text-xs text-slate-500">Recorded calls and SMS threads with AI summary, empathy scoring, missed-upsell flags, and advisor coaching. Owner-only — not advisor workflow.</p>
            </div>

            @if (session('status'))
                <div class="border-b border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900">{{ session('status') }}</div>
            @endif

            @unless ($analysisEnabled)
                <div class="border-b border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950">
                    Add an OpenAI API key under
                    <a href="{{ route('operations.settings.shop.edit', ['section' => 'ark-cloud']) }}" class="font-bold underline">Settings → ARK Platform</a>
                    to enable transcription and analysis.
                </div>
            @endunless

            <form method="GET" action="{{ route('operations.owner.call-intelligence') }}" class="grid gap-3 border-b border-slate-200 px-3 py-3 sm:grid-cols-2 lg:grid-cols-6">
                <label class="block text-xs">
                    <span class="font-bold uppercase tracking-wide text-slate-500">From</span>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                </label>
                <label class="block text-xs">
                    <span class="font-bold uppercase tracking-wide text-slate-500">To</span>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                </label>
                <label class="block text-xs">
                    <span class="font-bold uppercase tracking-wide text-slate-500">Channel</span>
                    <select name="channel" class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                        <option value="all" @selected($filters['channel'] === 'all')>Calls & SMS</option>
                        <option value="calls" @selected($filters['channel'] === 'calls')>Calls only</option>
                        <option value="sms" @selected($filters['channel'] === 'sms')>SMS only</option>
                    </select>
                </label>
                <label class="block text-xs">
                    <span class="font-bold uppercase tracking-wide text-slate-500">Media</span>
                    <select name="media" class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                        <option value="" @selected($filters['media'] === '')>All calls</option>
                        <option value="recorded" @selected($filters['media'] === 'recorded')>Recorded / voicemail</option>
                    </select>
                </label>
                <label class="block text-xs">
                    <span class="font-bold uppercase tracking-wide text-slate-500">Analysis</span>
                    <select name="analysis" class="mt-1 block w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm text-slate-950">
                        <option value="" @selected($filters['analysis'] === '')>Any status</option>
                        <option value="ready" @selected($filters['analysis'] === 'ready')>Analysis ready</option>
                        <option value="follow_up" @selected($filters['analysis'] === 'follow_up')>Follow-up flagged</option>
                        <option value="missed_upsell" @selected($filters['analysis'] === 'missed_upsell')>Missed upsell</option>
                        <option value="coaching" @selected($filters['analysis'] === 'coaching')>Needs coaching</option>
                        <option value="pinned" @selected($filters['analysis'] === 'pinned')>Pinned follow-up</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="rounded-sm border border-slate-800 bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">Apply</button>
                </div>
            </form>

            @include('operations.owner.partials.call-intelligence-coaching-queue', [
                'coachingRows' => $coachingRows,
                'filters' => $filters,
            ])

            <div class="space-y-3 bg-slate-100/80 p-3">
                @forelse ($rows as $row)
                    <article class="rounded-sm border border-slate-300 bg-white shadow-sm">
                        <div class="px-3 py-2.5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <h2 class="text-sm font-black text-slate-950">
                                        @if (($row['kind'] ?? 'call') === 'sms')
                                            <span class="mr-1 rounded-sm bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-900">SMS</span>
                                        @endif
                                        {{ $row['display_phone'] }}
                                        @if ($row['customer_name'])
                                            <span class="font-semibold text-slate-600">· {{ $row['customer_name'] }}</span>
                                        @endif
                                    </h2>
                                    <p class="mt-1 text-[11px] text-slate-500">
                                        {{ $row['started_at_label'] }}
                                        · {{ $row['direction_label'] }}
                                        · {{ $row['status_label'] }}
                                        · {{ $row['duration_label'] }}
                                        @if ($row['staff_name'])
                                            · {{ $row['staff_name'] }}
                                        @endif
                                        @if ($row['repair_order_number'])
                                            · <a href="{{ $row['repair_order_url'] }}" class="font-semibold text-slate-700 underline decoration-slate-300">RO #{{ $row['repair_order_number'] }}</a>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                                    @if ($row['recording_url'])
                                        <audio controls preload="none" class="h-8 max-w-[12rem]">
                                            <source src="{{ $row['recording_url'] }}" type="audio/mpeg">
                                        </audio>
                                    @endif
                                    @include('operations.owner.partials.call-intelligence-follow-up-pin', ['row' => $row])
                                    @if ($row['coaching_pdf_url'])
                                        <a
                                            href="{{ $row['coaching_pdf_url'] }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                                        >Handout</a>
                                    @endif
                                    <a
                                        href="{{ $row['show_url'] }}?{{ http_build_query($filters) }}"
                                        class="rounded-sm border border-slate-800 bg-slate-900 px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-slate-800"
                                    >{{ $row['open_label'] ?? 'Open call' }}</a>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 px-3 py-2">
                            @include('operations.owner.partials.call-intelligence-call-details', ['row' => $row, 'compact' => true])
                        </div>

                        <div class="border-t border-slate-100 px-3 py-2.5">
                            @if ($row['summary'])
                                <p class="text-sm leading-5 text-slate-800">{{ $row['summary'] }}</p>
                            @elseif ($row['analysis_error'])
                                <p class="text-xs font-semibold text-rose-800">{{ $row['analysis_error'] }}</p>
                            @else
                                <p class="text-xs text-slate-500">{{ $row['analysis_status_label'] }}@if ($row['analysis_status'] === 'pending' || $row['analysis_status'] === 'processing') — check back shortly @endif</p>
                            @endif

                            @if (! empty($row['topics']))
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach (array_slice($row['topics'], 0, 4) as $topic)
                                        <span class="rounded-sm border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ $topic }}</span>
                                    @endforeach
                                    @if (count($row['topics']) > 4)
                                        <span class="rounded-sm px-1.5 py-0.5 text-[10px] font-semibold text-slate-400">+{{ count($row['topics']) - 4 }} more</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-sm border border-slate-300 bg-white px-3 py-6 text-sm text-slate-500">No calls match this range.</div>
                @endforelse
            </div>

            @if ($paginator->hasPages())
                <div class="border-t border-slate-200 px-3 py-3">
                    {{ $paginator->links() }}
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('operations.owner.day-review') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Day Review</a>
            <a href="{{ route('operations.reports.operational') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Operational report</a>
        </div>
    </section>
</x-operations.app>
