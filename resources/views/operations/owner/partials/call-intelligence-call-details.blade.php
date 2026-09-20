@props(['row', 'compact' => false])

@include('operations.owner.partials.call-intelligence-signal-groups', [
    'row' => $row,
    'compact' => $compact,
    'showMeta' => true,
])

@if (! $compact && ($row['empathy_notes'] || $row['follow_up_notes'] || $row['missed_upsell_notes'] || $row['appointment_notes'] || $row['coaching_notes'] || ! empty($row['coaching_strengths']) || ! empty($row['coaching_improvements'])))
    <div @class(['grid gap-3 sm:grid-cols-2', 'mt-3' => ! $compact, 'mt-2' => $compact])>
        @if ($row['customer_intent'] || $row['outcome'] || $row['follow_up_notes'])
            <div class="rounded-sm border border-sky-200/90 bg-sky-50/50 px-3 py-2.5 sm:col-span-2">
                <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-sky-900">Customer detail</p>
                <dl class="mt-2 grid gap-2 text-xs sm:grid-cols-2">
                    @if ($row['customer_intent'])
                        <div class="sm:col-span-2">
                            <dt class="font-bold uppercase tracking-wide text-sky-800/80">Intent</dt>
                            <dd class="mt-0.5 text-sky-950">{{ $row['customer_intent'] }}</dd>
                        </div>
                    @endif
                    @if ($row['outcome'])
                        <div>
                            <dt class="font-bold uppercase tracking-wide text-sky-800/80">Outcome</dt>
                            <dd class="mt-0.5 text-sky-950">{{ $row['outcome'] }}</dd>
                        </div>
                    @endif
                    @if ($row['follow_up_notes'])
                        <div class="sm:col-span-2">
                            <dt class="font-bold uppercase tracking-wide text-amber-800">Follow-up notes</dt>
                            <dd class="mt-0.5 text-amber-950">{{ $row['follow_up_notes'] }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        @if ($row['empathy_notes'] || $row['missed_upsell_notes'] || $row['appointment_notes'] || $row['coaching_notes'] || ! empty($row['coaching_strengths']) || ! empty($row['coaching_improvements']))
            <div class="rounded-sm border border-violet-200/90 bg-violet-50/50 px-3 py-2.5 sm:col-span-2">
                <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-violet-950">Advisor detail</p>
                <dl class="mt-2 grid gap-2 text-xs sm:grid-cols-2">
                    @if ($row['empathy_notes'])
                        <div class="sm:col-span-2">
                            <dt class="font-bold uppercase tracking-wide text-violet-900/80">Empathy evidence</dt>
                            <dd class="mt-0.5 text-violet-950">{{ $row['empathy_notes'] }}</dd>
                        </div>
                    @endif
                    @if ($row['missed_upsell_notes'])
                        <div class="sm:col-span-2">
                            <dt class="font-bold uppercase tracking-wide text-rose-800">Missed upsell</dt>
                            <dd class="mt-0.5 text-rose-950">{{ $row['missed_upsell_notes'] }}</dd>
                        </div>
                    @endif
                    @if ($row['appointment_notes'])
                        <div class="sm:col-span-2">
                            <dt class="font-bold uppercase tracking-wide text-violet-900/80">Appointment</dt>
                            <dd class="mt-0.5 text-violet-950">{{ $row['appointment_notes'] }}</dd>
                        </div>
                    @endif
                    @if ($row['coaching_notes'])
                        <div class="sm:col-span-2">
                            <dt class="font-bold uppercase tracking-wide text-violet-900/80">Coaching summary</dt>
                            <dd class="mt-0.5 text-violet-950">{{ $row['coaching_notes'] }}</dd>
                        </div>
                    @endif
                    @if (! empty($row['coaching_strengths']))
                        <div>
                            <dt class="font-bold uppercase tracking-wide text-emerald-800">Strengths</dt>
                            <dd class="mt-0.5 text-violet-950">
                                <ul class="list-disc space-y-0.5 pl-4">
                                    @foreach ($row['coaching_strengths'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </dd>
                        </div>
                    @endif
                    @if (! empty($row['coaching_improvements']))
                        <div>
                            <dt class="font-bold uppercase tracking-wide text-amber-800">Improve next time</dt>
                            <dd class="mt-0.5 text-violet-950">
                                <ul class="list-disc space-y-0.5 pl-4">
                                    @foreach ($row['coaching_improvements'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif
    </div>
@endif

@if (! $compact && filled($row['suggested_reply'] ?? null))
    <div class="rounded-sm border border-emerald-200/90 bg-emerald-50/60 px-3 py-2.5">
        <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-emerald-950">{{ ($row['kind'] ?? 'call') === 'sms' ? 'Suggested reply' : 'Suggested note' }}</p>
        <p class="mt-1 text-sm leading-6 text-emerald-950">{{ $row['suggested_reply'] }}</p>
    </div>
@endif
