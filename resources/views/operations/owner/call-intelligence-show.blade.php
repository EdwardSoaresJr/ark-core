<x-operations.app title="{{ ($row['kind'] ?? 'call') === 'sms' ? 'SMS' : 'Call' }} · {{ $row['display_phone'] }}">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Owner · Call & SMS intelligence</p>
                <div class="mt-1 flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="text-base font-black text-slate-950">
                            {{ $row['display_phone'] }}
                            @if ($row['customer_name'])
                                <span class="font-semibold text-slate-600">· {{ $row['customer_name'] }}</span>
                            @endif
                        </h1>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $row['started_at_label'] }}
                            · {{ $row['direction_label'] }}
                            · {{ $row['status_label'] }}
                            @if ($row['media_kind_label'])
                                · {{ $row['media_kind_label'] }}
                            @endif
                            · {{ $row['duration_label'] }}
                            @if ($row['staff_name'])
                                · {{ $row['staff_name'] }}
                            @endif
                            @if ($row['analyzed_at_label'])
                                · Analyzed {{ $row['analyzed_at_label'] }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        @if ($row['recording_url'])
                            <audio controls preload="none" class="h-8 max-w-[16rem]">
                                <source src="{{ $row['recording_url'] }}" type="audio/mpeg">
                            </audio>
                        @endif
                        @if ($row['customer_url'])
                            <a href="{{ $row['customer_url'] }}" class="rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-800 hover:border-slate-400">Customer hub</a>
                        @endif
                        @if ($row['repair_order_url'])
                            <a href="{{ $row['repair_order_url'] }}" class="rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-800 hover:border-slate-400">RO #{{ $row['repair_order_number'] }}</a>
                        @endif
                        @if ($row['can_reanalyze'])
                            <form method="POST" action="{{ $row['reanalyze_url'] }}">
                                @csrf
                                <button type="submit" class="rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-600 hover:border-slate-400 hover:text-slate-900">Re-analyze</button>
                            </form>
                        @endif
                        @include('operations.owner.partials.call-intelligence-follow-up-pin', ['row' => $row])
                        @if (($row['kind'] ?? 'call') === 'call')
                            <a
                                href="{{ \App\Ark\Runtime\Ecosystem\EcosystemArkademyBridge::advisorIncomingCallsUrl() }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-800 hover:border-slate-400"
                            >ARKademy · phone floor</a>
                        @endif
                        @if ($row['coaching_pdf_url'])
                            <a
                                href="{{ $row['coaching_pdf_url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-sm border border-slate-800 bg-slate-900 px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-slate-800"
                            >Print handout</a>
                        @endif
                    </div>
                </div>
            </div>

            @if (session('status'))
                <div class="border-b border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="border-b border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-900">{{ session('error') }}</div>
            @endif

            <div class="space-y-4 px-3 py-4">
                @include('operations.owner.partials.call-intelligence-call-details', ['row' => $row])

                @if ($row['summary'])
                    <div>
                        <h2 class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Summary</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-800">{{ $row['summary'] }}</p>
                    </div>
                @elseif ($row['analysis_error'])
                    <div class="rounded-sm border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-900">{{ $row['analysis_error'] }}</div>
                @elseif ($row['analysis_status'] === 'pending' || $row['analysis_status'] === 'processing')
                    <p class="text-sm text-slate-500">{{ $row['analysis_status_label'] }} — check back shortly.</p>
                @endif

                @if (! empty($row['topics']))
                    <div>
                        <h2 class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Topics</h2>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            @foreach ($row['topics'] as $topic)
                                <span class="rounded-sm border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ $topic }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($row['transcript'])
                    <div>
                        <h2 class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Transcript</h2>
                        <div class="mt-1.5 max-h-[32rem] overflow-y-auto rounded-sm border border-slate-200 bg-slate-50 px-3 py-3 text-sm leading-6 text-slate-800 whitespace-pre-wrap">{{ $row['transcript'] }}</div>
                    </div>
                @else
                    <p class="text-sm text-slate-500">No transcript available for this {{ ($row['kind'] ?? 'call') === 'sms' ? 'thread' : 'call' }}.</p>
                @endif

                @if ($showCoachingDebrief ?? true)
                    @include('operations.owner.partials.call-intelligence-coaching-debrief', [
                        'row' => $row,
                        'coachingStaffOptions' => $coachingStaffOptions,
                        'defaultCoachingStaffUserId' => $defaultCoachingStaffUserId,
                        'coachingLogs' => $coachingLogs,
                    ])
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ $listUrl }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">← All communications</a>
            <a href="{{ route('operations.owner.day-review') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Day Review</a>
        </div>
    </section>
</x-operations.app>
