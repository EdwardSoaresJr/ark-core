@if (count($coachingRows) > 0)
    <div class="border-b border-amber-200 bg-amber-50/50 px-3 py-3">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <h2 class="text-[11px] font-bold uppercase tracking-[0.08em] text-amber-900">Most needed coaching</h2>
                <p class="mt-0.5 text-xs text-amber-950/80">Pinned follow-ups stay at top. Remaining slots ranked by coaching priority, empathy gaps, and missed upsells.</p>
            </div>
            <a
                href="{{ route('operations.owner.call-intelligence', array_merge($filters, ['analysis' => 'coaching'])) }}"
                class="text-[11px] font-bold text-amber-950 underline decoration-amber-300 hover:text-amber-900"
            >View all coaching</a>
        </div>

        <ol class="mt-3 space-y-2">
            @foreach ($coachingRows as $index => $row)
                <li class="rounded-sm border border-amber-200/80 bg-white px-3 py-2.5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[11px] font-black tabular-nums text-amber-800">{{ $index + 1 }}.</span>
                                <p class="text-sm font-black text-slate-950">
                                    @if (($row['kind'] ?? 'call') === 'sms')
                                        <span class="mr-1 rounded-sm bg-sky-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-900">SMS</span>
                                    @endif
                                    {{ $row['display_phone'] }}
                                    @if ($row['customer_name'])
                                        <span class="font-semibold text-slate-600">· {{ $row['customer_name'] }}</span>
                                    @endif
                                </p>
                                @if ($row['coaching_follow_up_pinned'])
                                    <span class="rounded-sm bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-900">Pinned</span>
                                @endif
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">
                                {{ $row['started_at_label'] }}
                                @if ($row['staff_name'])
                                    · {{ $row['staff_name'] }}
                                @endif
                                · {{ $row['direction_label'] }}
                            </p>
                            @include('operations.owner.partials.call-intelligence-signal-groups', [
                                'row' => $row,
                                'compact' => true,
                                'showMeta' => false,
                            ])
                            @if ($row['coaching_headline'])
                                <p class="mt-1.5 text-sm leading-5 text-slate-800">{{ $row['coaching_headline'] }}</p>
                            @elseif ($row['summary'])
                                <p class="mt-1.5 text-sm leading-5 text-slate-700">{{ $row['summary'] }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @include('operations.owner.partials.call-intelligence-follow-up-pin', ['row' => $row])
                            @if ($row['coaching_pdf_url'])
                                <a
                                    href="{{ $row['coaching_pdf_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="shrink-0 rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-700 hover:border-slate-400"
                                >Handout</a>
                            @endif
                            <a
                                href="{{ $row['show_url'] }}?{{ http_build_query($filters) }}"
                                class="shrink-0 rounded-sm border border-slate-800 bg-slate-900 px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-slate-800"
                            >{{ $row['open_label'] ?? 'Open call' }}</a>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>
@endif
