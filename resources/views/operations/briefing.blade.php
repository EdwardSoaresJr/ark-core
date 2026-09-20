<x-operations.app title="Briefing">
    <section class="ops-briefing space-y-3" x-data="{ openItem: null }">
        <header class="border border-slate-300 bg-white px-4 py-3">
            <p class="text-lg font-black text-slate-950">{{ $briefing->greeting }}</p>
            <p class="mt-1 text-xs text-slate-500">
                {{ $briefing->briefingDateLabel }}
                · Rebuilt {{ $briefing->generatedAt->timezone(\App\Ark\Operations\Reports\OperationalReportDateScope::displayTimezone())->format('g:i A') }}
            </p>
        </header>

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $briefing->narrativeIntro }}</p>
                <p class="text-xs text-slate-500">Posted sales truth from operational reporting — not a dashboard.</p>
            </div>
            <dl class="divide-y divide-slate-100">
                @forelse ($briefing->yesterdaySummary as $row)
                    <div class="grid grid-cols-[9rem_1fr] gap-2 px-3 py-2.5 text-sm">
                        <dt class="font-bold text-slate-500">{{ $row['label'] }}</dt>
                        <dd>
                            <p class="font-black tabular-nums text-slate-950">{{ $row['value'] }}</p>
                            @if (! empty($row['hint']))
                                <p class="mt-0.5 text-xs text-slate-500">{{ $row['hint'] }}</p>
                            @endif
                        </dd>
                    </div>
                @empty
                    <div class="px-3 py-4 text-sm text-slate-500">No posted activity recorded for yesterday.</div>
                @endforelse
            </dl>
        </div>

        @if ($briefing->hasAttentionItems)
            @foreach ($briefing->sections as $section)
                <div class="border border-slate-300 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $section->title }}</p>
                        @if ($section->intro)
                            <p class="text-xs text-slate-500">{{ $section->intro }}</p>
                        @endif
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($section->items as $item)
                            <li class="px-3 py-3">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span @class([
                                                'rounded-sm px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide',
                                                'bg-red-100 text-red-900' => $item->priority === \App\Ark\Operations\Briefing\BriefingPriority::Critical,
                                                'bg-amber-100 text-amber-900' => $item->priority === \App\Ark\Operations\Briefing\BriefingPriority::High,
                                                'bg-slate-100 text-slate-700' => $item->priority === \App\Ark\Operations\Briefing\BriefingPriority::Normal,
                                                'bg-slate-50 text-slate-500' => $item->priority === \App\Ark\Operations\Briefing\BriefingPriority::Low,
                                            ])>{{ $item->priority->label() }}</span>
                                            <p class="text-sm font-bold text-slate-950">{{ $item->headline }}</p>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-600">{{ $item->summary }}</p>
                                    </div>
                                    @if ($item->actionUrl)
                                        <a href="{{ $item->actionUrl }}" class="shrink-0 rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-800 hover:border-slate-400">
                                            {{ $item->actionLabel ?? 'Open' }}
                                        </a>
                                    @endif
                                </div>

                                @if ($item->confidence->reason !== '')
                                    <div class="mt-2 rounded-sm border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs">
                                        <p class="font-bold text-slate-700">Why</p>
                                        <p class="mt-0.5 text-slate-600">{{ $item->confidence->reason }}</p>
                                        <p class="mt-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                            Confidence {{ $item->confidence->score }}%
                                        </p>
                                    </div>
                                @endif

                                @if ($item->evidenceItems !== [])
                                    <div class="mt-2">
                                        <button
                                            type="button"
                                            class="text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-800"
                                            @click="openItem = openItem === @js($item->key) ? null : @js($item->key)"
                                        >
                                            <span x-text="openItem === @js($item->key) ? 'Hide evidence' : 'Show me'"></span>
                                        </button>
                                        <div x-show="openItem === @js($item->key)" x-cloak class="mt-2 space-y-2">
                                            @if ($item->confidence->signals !== [])
                                                <ul class="space-y-1">
                                                    @foreach ($item->confidence->signals as $signal)
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

                                            @if ($item->confidence->facts !== [])
                                                <div class="rounded-sm border border-slate-200 bg-white px-2 py-1.5">
                                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">What</p>
                                                    <dl class="mt-1 space-y-0.5">
                                                        @foreach ($item->confidence->facts as $fact)
                                                            <div class="grid grid-cols-[5rem_1fr] gap-2 text-[11px]">
                                                                <dt class="text-slate-500">{{ $fact['label'] }}</dt>
                                                                <dd class="font-semibold text-slate-800">{{ $fact['value'] }}</dd>
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                </div>
                                            @endif

                                            <div class="rounded-sm border border-slate-200 bg-white px-2 py-1.5">
                                                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Underlying events</p>
                                                <ul class="mt-1 divide-y divide-slate-100">
                                                    @foreach ($item->evidenceItems as $evidence)
                                                        <li class="py-1.5 text-[11px]">
                                                            <p class="font-semibold text-slate-800">{{ $evidence->summary }}</p>
                                                            @if ($evidence->detail)
                                                                <p class="mt-0.5 text-slate-600">{{ $evidence->detail }}</p>
                                                            @endif
                                                            <p class="mt-0.5 text-slate-400">{{ $evidence->occurredAt->format('M j g:i A') }}</p>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        @else
            <div class="border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950">
                {{ $briefing->emptyAttentionMessage }}
            </div>
        @endif

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('operations.index') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Attention</a>
            <a href="{{ route('operations.workboard') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Workboard</a>
            <a href="{{ route('operations.reports.index') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Reports</a>
        </div>
    </section>
</x-operations.app>
