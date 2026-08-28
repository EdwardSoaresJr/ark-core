@php
    /** @var array<string, mixed> $marketPressure */
@endphp

<div id="market-pressure" class="mt-4 border border-slate-200 bg-white">
    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Market pressure</p>
        <p class="text-xs text-slate-500">{{ $marketPressure['period_label'] }} — is the shop becoming more trusted by the market?</p>
        @if ($marketPressure['diagnosis'])
            <p class="mt-2 text-sm font-semibold text-amber-950">{{ $marketPressure['diagnosis'] }}</p>
        @endif
    </div>

    <ul class="divide-y divide-slate-100">
        @foreach ($marketPressure['rows'] as $row)
            <li class="px-3 py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $row['label'] }}</p>
                        <p class="mt-0.5 text-sm font-black text-slate-950">{{ $row['headline'] }}</p>
                        @if (filled($row['why_care'] ?? null))
                            <p class="mt-2 text-xs leading-relaxed text-slate-700">{{ $row['why_care'] }}</p>
                        @elseif (filled($row['why'] ?? null))
                            <p class="mt-1 text-xs text-slate-600">{{ $row['why'] }}</p>
                        @endif
                        @if (! empty($row['evidence']))
                            <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                                @foreach ($row['evidence'] as $fact)
                                    <div class="text-[11px] text-slate-500">
                                        <dt class="inline font-semibold text-slate-600">{{ $fact['label'] }}:</dt>
                                        <dd class="inline tabular-nums text-slate-800">{{ $fact['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                    <span @class([
                        'shrink-0 rounded-sm px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                        'bg-amber-100 text-amber-900' => ($row['posture'] ?? '') === 'pressure',
                        'bg-emerald-100 text-emerald-900' => ($row['posture'] ?? '') === 'healthy',
                        'bg-slate-100 text-slate-600' => ($row['posture'] ?? '') === 'observe',
                    ])>{{ $row['posture'] ?? 'observe' }}</span>
                </div>

                @if ($row['key'] === 'missed_review_opportunities' && ! empty($row['detail_rows']))
                    <ul class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                        @foreach ($row['detail_rows'] as $detail)
                            <li class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div>
                                    <a href="{{ $detail['url'] }}" class="font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">
                                        {{ $detail['label'] }} · {{ $detail['vehicle_label'] }}
                                    </a>
                                    <p class="mt-0.5 text-[11px] text-slate-500">
                                        Closed {{ $detail['closed_label'] }} · {{ $detail['age_days'] }}d ago · {{ $detail['customer_email_hint'] }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-amber-800">{{ $detail['reason'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>

    @if (! empty($marketPressure['ledger']))
        <div class="grid gap-0 border-t border-slate-200 sm:grid-cols-2">
            @foreach (['effort' => 'Authority effort', 'earned' => 'Authority earned'] as $ledgerKey => $ledgerTitle)
                @php($ledger = $marketPressure['ledger'][$ledgerKey] ?? null)
                @if ($ledger)
                    <div @class(['border-slate-200 bg-slate-50 px-3 py-3', 'sm:border-r' => $ledgerKey === 'effort'])>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $ledgerTitle }} · {{ $ledger['period_label'] }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ $ledger['interpretation'] }}</p>
                        <ul class="mt-3 space-y-1">
                            @foreach ($ledger['entries'] as $entry)
                                <li class="flex items-center justify-between gap-3 text-xs">
                                    <span class="text-slate-700">{{ $entry['label'] }}</span>
                                    <span class="font-semibold tabular-nums text-slate-900">{{ number_format($entry['count']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
