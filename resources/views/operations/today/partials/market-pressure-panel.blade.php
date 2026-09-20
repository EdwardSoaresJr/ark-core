@php
    /** @var array<string, mixed> $panel */
    $barSegments = 8;
@endphp

<div class="border-b border-slate-200 px-3 py-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p @class([
                'text-sm font-black text-slate-950',
                'text-amber-950' => ($panel['posture'] ?? '') === 'pressure',
            ])>{{ $panel['headline'] }}</p>
            @if ($panel['eligible_closes'] > 0)
                <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                    <div>
                        <dt class="inline font-bold text-slate-500">Target:</dt>
                        <dd class="inline tabular-nums font-semibold text-slate-900">{{ $panel['target'] }}</dd>
                    </div>
                    @if ($panel['missed_count'] > 0)
                        <div>
                            <dt class="inline font-bold text-amber-800">Missed:</dt>
                            <dd class="inline tabular-nums font-semibold text-amber-900">{{ $panel['missed_count'] }} opportunities</dd>
                        </div>
                    @endif
                </dl>
            @endif
            @if (filled($panel['why_care'] ?? null))
                <p class="mt-2 text-xs leading-relaxed text-slate-700">{{ $panel['why_care'] }}</p>
            @endif
        </div>
        <span @class([
            'shrink-0 rounded-sm px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
            'bg-amber-100 text-amber-900' => ($panel['posture'] ?? '') === 'pressure',
            'bg-emerald-100 text-emerald-900' => ($panel['posture'] ?? '') === 'healthy',
        ])>{{ $panel['posture'] ?? 'observe' }}</span>
    </div>

    @if (! empty($panel['advisor_breakdown']))
        <div class="mt-4 border-t border-slate-100 pt-3">
            <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Advisor breakdown</p>
            <ul class="mt-2 space-y-2">
                @foreach ($panel['advisor_breakdown'] as $advisor)
                    @php
                        $filled = $advisor['paid_closes'] > 0
                            ? (int) round(($advisor['review_requests'] / $advisor['paid_closes']) * $barSegments)
                            : 0;
                    @endphp
                    <li class="text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900">{{ $advisor['name'] }}</span>
                            <span class="tabular-nums font-bold text-slate-700">{{ $advisor['review_requests'] }}/{{ $advisor['paid_closes'] }}</span>
                        </div>
                        <div class="mt-1 flex gap-0.5" aria-hidden="true">
                            @foreach (range(1, $barSegments) as $segment)
                                <span @class([
                                    'h-2 flex-1 rounded-sm',
                                    'bg-[#0099cc]' => $segment <= $filled,
                                    'bg-slate-200' => $segment > $filled,
                                ])></span>
                            @endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="mt-3 text-[11px]">
        <a href="{{ $panel['detail_url'] }}" class="font-bold text-[#0099cc] no-underline hover:text-[#0088b8]">Full market pressure →</a>
    </p>
</div>
