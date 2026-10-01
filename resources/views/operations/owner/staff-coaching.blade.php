<x-operations.app :title="$staffMember->name . ' · Coaching'">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Owner · Team coaching</p>
                <h1 class="mt-0.5 text-base font-black text-slate-950">{{ $staffMember->name }}</h1>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $staffMember->email }}
                    @if ($staffMember->display_phone)
                        · {{ $staffMember->display_phone }}
                    @endif
                    · {{ $coachingLogCount }} {{ Str::plural('coaching log', $coachingLogCount) }}
                </p>
                @if ($staffMember->staffRoleLabels())
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach ($staffMember->staffRoleLabels() as $roleLabel)
                            <span class="rounded-sm bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">{{ $roleLabel }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="divide-y divide-slate-200">
                @forelse ($coachingLogs as $log)
                    <article class="px-3 py-3">
                        <p class="text-[11px] font-bold text-slate-500">
                            {{ $log['discussed_at_label'] }}
                            @if ($log['recorded_by_name'])
                                · logged by {{ $log['recorded_by_name'] }}
                            @endif
                        </p>
                        @if (! empty($log['call_url']))
                            <p class="mt-1 text-[11px] text-slate-600">
                                @if ($log['call_started_at_label'])
                                    Call {{ $log['call_started_at_label'] }}
                                @else
                                    Linked call
                                @endif
                                @if ($log['customer_name'])
                                    · {{ $log['customer_name'] }}
                                @endif
                                · <a href="{{ $log['call_url'] }}" class="font-bold text-slate-800 underline decoration-slate-300">Open call</a>
                            </p>
                            @if ($log['call_summary'])
                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ $log['call_summary'] }}</p>
                            @endif
                        @endif
                        <p class="mt-2 text-sm leading-6 text-slate-800 whitespace-pre-wrap">{{ $log['notes'] }}</p>
                    </article>
                @empty
                    <p class="px-3 py-6 text-sm text-slate-500">No coaching debriefs logged for {{ $staffMember->name }} yet. Log one from a call on Call intelligence.</p>
                @endforelse
            </div>
        </div>

        <div class="flex flex-wrap gap-2 text-xs">
            <a href="{{ route('operations.owner.call-intelligence') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Call intelligence</a>
            <a href="{{ route('operations.settings.shop.edit', ['section' => 'staff']) }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Team settings</a>
            <a href="{{ route('operations.owner.day-review') }}" class="rounded-sm border border-slate-300 bg-white px-3 py-2 font-bold text-slate-800 hover:border-slate-400">Day Review</a>
        </div>
    </section>
</x-operations.app>
