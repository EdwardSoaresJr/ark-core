@props([
    'followUps' => [],
    'tasks' => [],
    'redirect' => null,
])

@php
    $redirectUrl = $redirect ?? url()->current();
    $hasWork = $followUps !== [] || $tasks !== [];
@endphp

@if ($hasWork)
    <section class="ops-work-context-panel border-t border-slate-200">
        @if ($followUps !== [])
            <div class="border-b border-slate-100">
                <p class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-wide text-violet-700">Open Follow-Ups</p>
                <ul class="divide-y divide-slate-100">
                    @foreach ($followUps as $row)
                        <li class="ops-work-item-row ops-work-item-row--follow-up">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    @if (filled($row['customer_name'] ?? null))
                                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                            <p class="text-sm font-black text-violet-950">{{ $row['customer_name'] }}</p>
                                            @if (filled($row['dollars_at_risk_label'] ?? null))
                                                <p class="text-sm font-black tabular-nums text-violet-900">{{ $row['dollars_at_risk_label'] }}</p>
                                            @endif
                                        </div>
                                    @endif
                                    <p class="{{ filled($row['customer_name'] ?? null) ? 'mt-0.5' : '' }} text-sm font-bold text-slate-950">{{ $row['notes'] }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">
                                        <span class="font-semibold text-slate-700">Assigned to {{ $row['assigned_to_label'] }}</span>
                                        @if (filled($row['schedule_label'] ?? null))
                                            <span>·</span>
                                            <span class="font-semibold text-violet-800">{{ $row['schedule_label'] }}</span>
                                            @if (filled($row['due_time_label'] ?? null))
                                                <span>at {{ $row['due_time_label'] }}</span>
                                            @endif
                                        @endif
                                    </p>
                                </div>
                                <form method="POST" action="{{ $row['complete_url'] }}" class="shrink-0">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="redirect" value="{{ $redirectUrl }}">
                                    <button type="submit" class="ops-call-queue__action ops-call-queue__action--ghost">Done</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($tasks !== [])
            <div>
                <p class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-wide text-emerald-800">Open Tasks</p>
                <ul class="divide-y divide-slate-100">
                    @foreach ($tasks as $row)
                        <li class="ops-work-item-row ops-work-item-row--task">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-emerald-800">Shop task</p>
                                    <p class="mt-0.5 text-sm font-bold text-slate-950">{{ $row['notes'] }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">
                                        <span class="font-semibold text-slate-700">Assigned to {{ $row['assigned_to_label'] }}</span>
                                        @if (filled($row['schedule_label'] ?? null))
                                            <span>·</span>
                                            <span class="font-semibold text-emerald-800">{{ $row['schedule_label'] }}</span>
                                            @if (filled($row['due_time_label'] ?? null))
                                                <span>at {{ $row['due_time_label'] }}</span>
                                            @endif
                                        @endif
                                    </p>
                                </div>
                                <form method="POST" action="{{ $row['complete_url'] }}" class="shrink-0">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="redirect" value="{{ $redirectUrl }}">
                                    <button type="submit" class="ops-call-queue__action ops-call-queue__action--ghost">Done</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endif
