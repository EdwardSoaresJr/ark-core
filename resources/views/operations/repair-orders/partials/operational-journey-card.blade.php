@php
    $journey = $operationalJourney ?? null;
    $comparison = $journeyComparison ?? null;
    $identity = $journey?->identityConfidence;
@endphp

@if ($journey !== null && $journey->hasStory)
    <div
        class="border border-slate-200 bg-white"
        x-data="{ openMilestone: null, identityOpen: false }"
    >
        <div class="border-b border-slate-100 px-3 py-2">
            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Operational Journey</p>
            <p class="mt-0.5 text-xs text-slate-500">Explain why this repair order exists</p>
        </div>

        @if ($identity !== null)
            <div class="border-b border-slate-100 px-3 py-2 text-xs">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-2 text-left"
                    @click="identityOpen = ! identityOpen"
                >
                    <span class="font-bold text-slate-700">
                        Identity confidence
                        <span class="tabular-nums text-slate-900">{{ $identity->score }}%</span>
                    </span>
                    <span
                        class="text-[10px] font-bold uppercase tracking-wide text-slate-400"
                        x-text="identityOpen ? 'Hide' : 'Why?'"
                    ></span>
                </button>

                <div x-show="identityOpen" x-cloak class="mt-2 space-y-2">
                    <p class="text-[11px] text-slate-600">{{ $identity->reason }}</p>

                    @if ($identity->signals !== [])
                        <ul class="space-y-1">
                            @foreach ($identity->signals as $signal)
                                <li class="flex items-start gap-1.5 text-[11px]">
                                    <span @class([
                                        'font-bold',
                                        'text-emerald-700' => $signal['satisfied'],
                                        'text-slate-400' => ! $signal['satisfied'],
                                    ])>{{ $signal['satisfied'] ? '✓' : '·' }}</span>
                                    <span class="text-slate-700">{{ $signal['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($identity->facts !== [])
                        <div class="rounded-sm border border-slate-200 bg-slate-50 px-2 py-1.5">
                            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Confidence evidence</p>
                            <dl class="mt-1 space-y-0.5">
                                @foreach ($identity->facts as $fact)
                                    <div class="grid grid-cols-[5rem_1fr] gap-2">
                                        <dt class="text-slate-500">{{ $fact['label'] }}</dt>
                                        <dd class="font-semibold text-slate-800">{{ $fact['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <dl class="divide-y divide-slate-100 text-xs">
            @foreach ($journey->summaryCards as $card)
                <div class="px-3 py-2">
                    <div class="grid grid-cols-[7rem_1fr] gap-2">
                        <dt class="font-bold text-slate-500">{{ $card['label'] }}</dt>
                        <dd>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $card['value'] }}</p>
                                    @if ($card['meta'])
                                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $card['meta'] }}</p>
                                    @endif
                                </div>
                                @if (($card['expandable'] ?? false) && ($card['evidence_items'] ?? []) !== [])
                                    <button
                                        type="button"
                                        class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-sky-700 hover:text-sky-900"
                                        @click="openMilestone = openMilestone === '{{ $card['milestone_key'] }}' ? null : '{{ $card['milestone_key'] }}'"
                                    >
                                        <span x-text="openMilestone === '{{ $card['milestone_key'] }}' ? 'Hide' : 'Evidence'"></span>
                                    </button>
                                @endif
                            </div>

                            @if (($card['expandable'] ?? false) && ($card['evidence_items'] ?? []) !== [])
                                <div
                                    x-show="openMilestone === '{{ $card['milestone_key'] }}'"
                                    x-cloak
                                    class="mt-2 rounded-sm border border-slate-200 bg-slate-50 px-2 py-1.5"
                                >
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Evidence</p>
                                    <ul class="mt-1 space-y-1.5">
                                        @foreach ($card['evidence_items'] as $item)
                                            <li class="text-[11px]">
                                                <p class="font-semibold text-slate-800">
                                                    <span class="text-slate-500">{{ $item['occurred_label'] }}</span>
                                                    · {{ $item['summary'] }}
                                                </p>
                                                @if ($item['detail'])
                                                    <p class="mt-0.5 text-slate-600">{{ $item['detail'] }}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </dd>
                    </div>
                </div>
            @endforeach
        </dl>

        @if ($comparison !== null && ($comparison['customer_path'] ?? []) !== [])
            <div class="border-t border-slate-100 px-3 py-2 text-[11px] text-slate-600">
                <p class="font-bold text-slate-700">Journey comparison</p>
                <div class="mt-1 grid gap-2 sm:grid-cols-2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Average</p>
                        <p class="mt-0.5">{{ implode(' → ', $comparison['average_path'] ?? []) }}</p>
                        @if ($comparison['average_duration_days'] ?? null)
                            <p class="text-slate-500">{{ $comparison['average_duration_days'] }} days</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">This customer</p>
                        <p class="mt-0.5">{{ implode(' → ', $comparison['customer_path'] ?? []) }}</p>
                        @if ($comparison['customer_duration_days'] ?? null)
                            <p class="text-slate-500">{{ $comparison['customer_duration_days'] }} days</p>
                        @endif
                    </div>
                </div>
                @if ($comparison['narrative'] ?? null)
                    <p class="mt-2 text-slate-500">{{ $comparison['narrative'] }}</p>
                @endif
            </div>
        @endif
    </div>
@elseif ($journey !== null && ! $journey->hasStory)
    <div class="border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
        <p class="font-bold text-slate-600">Operational Journey</p>
        <p class="mt-0.5">No journey story is available for this repair order yet.</p>
    </div>
@endif
