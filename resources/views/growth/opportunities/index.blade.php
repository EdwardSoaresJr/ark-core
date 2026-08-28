<x-operations.app title="Growth · Opportunities">
    <section class="space-y-3" x-data="{ openId: null }">
        @include('growth.partials.subnav')

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">ARK Growth</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">Opportunity Queue</h1>
                <p class="mt-1 max-w-3xl text-xs text-slate-500">
                    What should we publish or improve next? The queue decides — build one excellent page, then measure.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            <div class="border border-emerald-300 bg-emerald-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-800">Validated</p>
                <p class="text-xl font-black text-emerald-950">{{ $queue['posture']['validated'] }}</p>
            </div>
            <div class="border border-sky-300 bg-sky-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-sky-800">Measuring</p>
                <p class="text-xl font-black text-sky-950">{{ $queue['posture']['measuring'] }}</p>
            </div>
            <div class="border border-amber-300 bg-amber-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-amber-900">Building</p>
                <p class="text-xl font-black text-amber-950">{{ $queue['posture']['building'] }}</p>
            </div>
            <div class="border border-slate-300 bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Accepted</p>
                <p class="text-xl font-black text-slate-950">{{ $queue['posture']['accepted'] }}</p>
            </div>
            <div class="border border-slate-300 bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Discovered</p>
                <p class="text-xl font-black text-slate-950">{{ $queue['posture']['discovered'] }}</p>
            </div>
            <div class="border border-slate-300 bg-white px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Published</p>
                <p class="text-xl font-black text-slate-950">{{ $queue['posture']['published'] }}</p>
            </div>
        </div>

        <div class="border border-slate-300 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Last synchronized</p>
                    <p class="text-xs text-slate-500">ARK maintains Growth automatically — no manual commands.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('growth.integrations.index') }}" class="rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-700 hover:border-slate-400 no-underline">
                        Integrations
                    </a>
                    <form method="POST" action="{{ route('growth.maintenance.rebuild') }}">
                        @csrf
                        <button type="submit" class="rounded-sm border border-slate-300 bg-white px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-700 hover:border-slate-400">
                            Rebuild now
                        </button>
                    </form>
                </div>
            </div>
            <ul class="divide-y divide-slate-100 px-3 py-1">
                @foreach ($sync['tasks'] as $task)
                    <li class="flex flex-wrap items-start justify-between gap-2 py-2 text-xs">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900">{{ $task['label'] }}</p>
                            @if (filled($task['message']))
                                <p class="mt-0.5 text-slate-500">{{ $task['message'] }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            @if ($task['healthy'])
                                <span class="font-bold text-emerald-700">{{ $task['status_label'] }} ✓</span>
                            @elseif ($task['status'] === 'failed')
                                <span class="font-bold text-rose-700">⚠ {{ $task['status_label'] }}</span>
                            @elseif ($task['status'] === 'pending')
                                <span class="font-bold text-amber-700">{{ $task['status_label'] }}</span>
                            @else
                                <span class="font-bold text-slate-600">{{ $task['status_label'] }}</span>
                            @endif
                            <p class="mt-0.5 text-[10px] text-slate-400">{{ $task['last_ran_label'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        @if (session('status'))
            <div class="border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-950">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-950">{{ $errors->first() }}</div>
        @endif

        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">
                    Top {{ count($queue['opportunities']) }} highest-value SEO tasks
                </p>
                <p class="text-xs text-slate-500">Action · Impact · Evidence · Effort · Acceptance · Status</p>
            </div>

            @if ($queue['opportunities'] === [])
                <div class="px-3 py-6 text-sm text-slate-500">
                    No opportunities yet. ARK will populate this queue after Search Console synchronizes — usually overnight, or use <span class="font-semibold text-slate-700">Rebuild now</span> above.
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($queue['opportunities'] as $item)
                        <li class="px-3 py-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($item['is_validated'])
                                            <span class="rounded-sm bg-emerald-600 px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-white">✓ Validated</span>
                                        @endif
                                        <span class="rounded-sm bg-slate-900 px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-white">{{ $item['effort'] }}</span>
                                        <span class="rounded-sm bg-sky-100 px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-sky-900">{{ $item['status'] }}</span>
                                        <p class="text-sm font-bold text-slate-950">{{ $item['title'] }}</p>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-600">{{ $item['impact'] }}</p>

                                    @if (! empty($item['acceptance_progress']))
                                        <p class="mt-1.5 text-[11px] font-semibold text-slate-600">
                                            Acceptance {{ $item['acceptance_progress']['satisfied'] }}/{{ $item['acceptance_progress']['required'] }}
                                            @if ($item['can_publish'])
                                                <span class="text-emerald-700">· ready to publish</span>
                                            @endif
                                        </p>
                                    @endif

                                    @if (! empty($item['estimated_lift']['steps']))
                                        <div class="mt-2 rounded-sm border border-slate-200 bg-slate-50 px-2.5 py-2 text-[11px]">
                                            <p class="font-bold text-slate-700">Estimated lift</p>
                                            <ul class="mt-1 space-y-0.5">
                                                @foreach ($item['estimated_lift']['steps'] as $step)
                                                    <li class="flex gap-2">
                                                        <span class="w-24 shrink-0 text-slate-500">{{ $step['label'] }}</span>
                                                        <span class="font-semibold text-slate-800">{{ $step['value'] }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    @if (! empty($item['report_card']) && ! ($item['report_card']['awaiting_data'] ?? true))
                                        <div class="mt-2 rounded-sm border border-emerald-200 bg-emerald-50 px-2.5 py-2 text-[11px]">
                                            <p class="font-bold text-emerald-900">Report card</p>
                                            <p class="mt-1 text-emerald-950">
                                                +{{ number_format($item['report_card']['impressions']) }} impressions ·
                                                +{{ number_format($item['report_card']['clicks']) }} clicks ·
                                                +{{ number_format($item['report_card']['leads']) }} leads ·
                                                ${{ number_format($item['report_card']['revenue_cents'] / 100, 0) }}
                                            </p>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    @if (in_array($item['status_value'], ['discovered', 'accepted'], true))
                                        <form method="POST" action="{{ $item['start_url'] }}">
                                            @csrf
                                            <button type="submit" class="rounded-sm border border-slate-900 bg-slate-900 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-white hover:bg-slate-800">
                                                Start →
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ $item['build_url'] }}" class="rounded-sm border border-slate-900 bg-slate-900 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-white hover:bg-slate-800">
                                            Continue →
                                        </a>
                                    @endif
                                    @if ($item['transitions'] !== [])
                                        <form method="POST" action="{{ route('growth.opportunities.status', $item['id']) }}">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="rounded-sm border border-slate-300 bg-white px-2 py-1.5 text-xs font-bold text-slate-800" onchange="this.form.submit()">
                                                <option value="{{ $item['status_value'] }}" selected>{{ $item['status'] }}</option>
                                                @foreach ($item['transitions'] as $transition)
                                                    <option value="{{ $transition }}">{{ ucfirst($transition) }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-3">
                                <button type="button" class="text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-800" @click="openId = openId === {{ $item['id'] }} ? null : {{ $item['id'] }}">
                                    <span x-text="openId === {{ $item['id'] }} ? 'Hide evidence' : 'Show evidence'"></span>
                                </button>
                                <button type="button" class="text-[10px] font-bold uppercase tracking-wide text-slate-500 hover:text-slate-800" @click="openId = openId === -{{ $item['id'] }} ? null : -{{ $item['id'] }}">
                                    <span x-text="openId === -{{ $item['id'] }} ? 'Hide done means' : 'Done means'"></span>
                                </button>
                            </div>

                            <div x-show="openId === {{ $item['id'] }}" x-cloak class="mt-2 space-y-2">
                                @if (! empty($item['evidence']['signals']))
                                    <ul class="space-y-1">
                                        @foreach ($item['evidence']['signals'] as $signal)
                                            <li class="flex items-start gap-1.5 text-[11px]">
                                                <span @class(['font-bold', 'text-emerald-700' => $signal['satisfied'], 'text-slate-400' => ! $signal['satisfied']])>{{ $signal['satisfied'] ? '✓' : '·' }}</span>
                                                <span class="text-slate-700">{{ $signal['label'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div x-show="openId === -{{ $item['id'] }}" x-cloak class="mt-2">
                                <ul class="space-y-1">
                                    @foreach ($item['acceptance_criteria'] as $criterion)
                                        <li class="flex items-start gap-1.5 text-[11px]">
                                            <span @class(['font-bold', 'text-emerald-700' => $criterion['satisfied'], 'text-slate-400' => ! $criterion['satisfied']])>{{ $criterion['satisfied'] ? '✓' : '·' }}</span>
                                            <span class="text-slate-700">{{ $criterion['label'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($queue['in_flight'] !== [])
            <div class="border border-slate-300 bg-white">
                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">In flight</p>
                </div>
                <ul class="divide-y divide-slate-100 text-xs">
                    @foreach ($queue['in_flight'] as $item)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                            <div class="flex items-center gap-2">
                                @if ($item['is_validated'])
                                    <span class="text-[10px] font-black uppercase text-emerald-700">✓ Validated</span>
                                @endif
                                <a href="{{ $item['build_url'] }}" class="font-bold text-slate-900 hover:text-sky-800">{{ $item['title'] }}</a>
                            </div>
                            <span class="text-slate-500">{{ $item['status'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('growth.content.index') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Content registry</a>
            <a href="{{ route('growth.dashboard') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Health dashboard</a>
        </div>
    </section>
</x-operations.app>
