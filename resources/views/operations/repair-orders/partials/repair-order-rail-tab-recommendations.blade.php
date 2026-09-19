@php
    $workspace = $recommendationsWorkspace ?? [
        'recommendations' => collect(),
        'open_count' => 0,
        'safety_count' => 0,
        'follow_up_due_count' => 0,
        'estimated_cents' => 0,
        'current_mileage' => null,
        'decision_reasons' => [],
        'resolved_reasons' => [],
        'due_kinds' => [],
        'urgencies' => [],
    ];
    $canManage = (bool) ($canManageRecommendations ?? false);
    $isTerminal = (bool) ($isTerminal ?? false);
    $displayTz = \App\Ark\Operations\Settings\ShopDisplayTimezone::resolve();
    $money = fn (?int $cents): string => $cents ? '$'.number_format($cents / 100, 2) : '—';
    $when = fn ($value) => $value?->timezone($displayTz)->format('M j, Y') ?? '—';
@endphp

<div id="recommendations-rail" class="ops-review-rail-tab-panel ops-recommendations-workspace">
    <div class="ops-recommendations-workspace__header">
        <h2 class="ops-recommendations-workspace__title">What this vehicle still needs</h2>
        @if ($workspace['open_count'] > 0 && ($workspace['safety_count'] > 0 || $workspace['follow_up_due_count'] > 0 || $workspace['estimated_cents'] > 0 || $workspace['current_mileage']))
            <p class="ops-recommendations-workspace__meta">
                @if ($workspace['safety_count'] > 0)
                    <span class="font-semibold text-rose-800">{{ $workspace['safety_count'] }} safety</span>
                @endif
                @if ($workspace['follow_up_due_count'] > 0)
                    @if ($workspace['safety_count'] > 0) · @endif
                    {{ $workspace['follow_up_due_count'] }} follow-up due
                @endif
                @if ($workspace['estimated_cents'] > 0)
                    @if ($workspace['safety_count'] > 0 || $workspace['follow_up_due_count'] > 0) · @endif
                    {{ $money($workspace['estimated_cents']) }} previously estimated
                @endif
                @if ($workspace['current_mileage'])
                    @if ($workspace['safety_count'] > 0 || $workspace['follow_up_due_count'] > 0 || $workspace['estimated_cents'] > 0) · @endif
                    {{ number_format($workspace['current_mileage']) }} mi now
                @endif
            </p>
        @endif
    </div>

    @if ($workspace['open_count'] === 0)
        <p class="ops-recommendations-workspace__empty">No open recommendations for this vehicle.</p>
    @endif

    @if ($canManage && ! $isTerminal)
        <form
            method="post"
            action="{{ route('operations.repair-orders.recommendations.store', $repairOrder) }}"
            class="ops-rec-create"
            x-data="{ open: false, notes: false, dueKind: 'now' }"
        >
            @csrf
            <button type="button" class="ops-rec-create__toggle" @click="open = ! open">
                + Record recommendation
            </button>
            <div class="ops-rec-create__body" x-show="open" x-cloak>
                <label class="ops-rec-create__title">
                    <span>Recommendation</span>
                    <input name="title" required maxlength="180" placeholder="Replace front brake pads &amp; rotors">
                </label>
                <div class="ops-rec-create__row">
                    <label>
                        <span>Urgency</span>
                        <select name="urgency">
                            @foreach ($workspace['urgencies'] as $urgency)
                                <option value="{{ $urgency['value'] }}">{{ $urgency['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Due</span>
                        <select name="due_kind" x-model="dueKind">
                            @foreach ($workspace['due_kinds'] as $kind)
                                <option value="{{ $kind['value'] }}" @selected($kind['value'] === 'now')>{{ $kind['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="ops-rec-create__due" x-show="dueKind === 'date' || dueKind === 'date_or_mileage'" x-cloak>
                        <span>Date</span>
                        <input type="date" name="due_on" x-bind:disabled="dueKind !== 'date' && dueKind !== 'date_or_mileage'">
                    </label>
                    <label class="ops-rec-create__due" x-show="dueKind === 'mileage' || dueKind === 'date_or_mileage'" x-cloak>
                        <span>Mileage</span>
                        <input type="number" name="due_mileage" min="0" x-bind:disabled="dueKind !== 'mileage' && dueKind !== 'date_or_mileage'">
                    </label>
                    <label class="ops-rec-create__safety">
                        <input type="checkbox" name="safety_related" value="1">
                        Safety
                    </label>
                    <button type="submit" class="ops-rec-create__save">Save</button>
                </div>
                <button type="button" class="ops-rec-create__notes-toggle" @click="notes = ! notes">
                    <span x-show="! notes">Add notes</span>
                    <span x-show="notes" x-cloak>Hide notes</span>
                </button>
                <label class="ops-rec-create__notes" x-show="notes" x-cloak>
                    <span>Customer description</span>
                    <textarea name="customer_description" rows="2"></textarea>
                </label>
            </div>
        </form>
    @endif

    <div class="divide-y divide-slate-100">
        @foreach ($workspace['recommendations'] as $row)
            <article
                class="px-3 py-2.5"
                x-data="{ open: false, decide: false, follow: false, resolve: false }"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                            <h3 class="text-sm font-black text-slate-950">{{ $row['title'] }}</h3>
                            @if ($row['safety_related'])
                                <span class="rounded-sm bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.06em] text-rose-800">Safety</span>
                            @endif
                            @if ($row['service_due'])
                                <span class="rounded-sm bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.06em] text-amber-900">Due</span>
                            @endif
                            @if ($row['on_current_estimate'])
                                <span class="rounded-sm bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-[0.06em] text-emerald-800">On estimate</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-[11px] leading-4 text-slate-500">
                            {{ $row['urgency_label'] }}
                            · Found {{ $when($row['discovered_at']) }}
                            @if ($row['discovered_mileage'])
                                @ {{ number_format($row['discovered_mileage']) }} mi
                            @endif
                            @if ($row['due_mileage'])
                                · Due {{ number_format($row['due_mileage']) }} mi
                            @endif
                            @if ($row['due_on'])
                                · Due {{ $row['due_on']->timezone($displayTz)->format('M j') }}
                            @endif
                            · Last: {{ $row['last_decision'] ?? ($row['was_presented'] ? 'Presented' : 'Not presented') }}
                            @if ($row['last_decision_reason'])
                                — {{ $row['last_decision_reason'] }}
                            @endif
                            · {{ $money($row['estimated_cents']) }}
                        </p>
                        @if ($row['follow_up_at'])
                            <p class="text-[11px] {{ $row['follow_up_due'] ? 'font-semibold text-amber-900' : 'text-slate-500' }}">
                                Follow up {{ $row['follow_up_at']->timezone($displayTz)->format('M j, g:i A') }}
                                @if ($row['follow_up_owner_name'])
                                    · {{ $row['follow_up_owner_name'] }}
                                @endif
                            </p>
                        @endif
                        @if ($row['source_finding_label'])
                            <p class="text-[11px] text-slate-500">Inspection: {{ $row['source_finding_label'] }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-1">
                        @if ($canManage && ! $isTerminal && ! $row['on_current_estimate'])
                            <form method="post" action="{{ route('operations.repair-orders.recommendations.add-to-estimate', [$repairOrder, $row['id']]) }}">
                                @csrf
                                <button class="rounded-sm border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-800 hover:border-slate-400">Add to Estimate</button>
                            </form>
                        @endif
                        @if ($canManage && ! $isTerminal)
                            <form method="post" action="{{ route('operations.repair-orders.recommendations.present', [$repairOrder, $row['id']]) }}">
                                @csrf
                                <button class="rounded-sm border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-800 hover:border-slate-400">Present</button>
                            </form>
                            <button type="button" class="rounded-sm border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-800" @click="decide = ! decide">Decision</button>
                            <button type="button" class="rounded-sm border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-800" @click="follow = ! follow">Follow-up</button>
                            <button type="button" class="rounded-sm border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-800" @click="resolve = ! resolve">Resolve</button>
                        @endif
                        <button type="button" class="rounded-sm border border-transparent px-2 py-1 text-[11px] font-semibold text-slate-600 hover:text-slate-950" @click="open = ! open">History</button>
                    </div>
                </div>

                @if ($canManage && ! $isTerminal)
                    <form method="post" action="{{ route('operations.repair-orders.recommendations.decision', [$repairOrder, $row['id']]) }}" class="mt-2 grid gap-2 border border-slate-200 bg-slate-50 p-2 sm:grid-cols-4" x-show="decide" x-cloak>
                        @csrf
                        <label class="text-[11px] font-semibold text-slate-600">
                            Decision
                            <select name="decision" class="mt-0.5 w-full border border-slate-300 px-2 py-1 text-sm">
                                <option value="approved">Authorized</option>
                                <option value="declined">Declined</option>
                                <option value="deferred">Deferred</option>
                            </select>
                        </label>
                        <label class="text-[11px] font-semibold text-slate-600">
                            Reason
                            <select name="reason_code" class="mt-0.5 w-full border border-slate-300 px-2 py-1 text-sm">
                                <option value="">Optional</option>
                                @foreach ($workspace['decision_reasons'] as $reason)
                                    <option value="{{ $reason['value'] }}">{{ $reason['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-[11px] font-semibold text-slate-600">
                            Follow up
                            <input type="datetime-local" name="follow_up_at" class="mt-0.5 w-full border border-slate-300 px-2 py-1 text-sm">
                        </label>
                        <label class="text-[11px] font-semibold text-slate-600 sm:col-span-4">
                            Note
                            <input name="note" class="mt-0.5 w-full border border-slate-300 px-2 py-1 text-sm font-normal">
                        </label>
                        <button class="rounded-sm bg-slate-950 px-3 py-1.5 text-[11px] font-bold text-white">Record</button>
                    </form>

                    <form method="post" action="{{ route('operations.repair-orders.recommendations.follow-up', [$repairOrder, $row['id']]) }}" class="mt-2 flex flex-wrap items-end gap-2 border border-slate-200 bg-slate-50 p-2" x-show="follow" x-cloak>
                        @csrf
                        <input type="hidden" name="action" value="schedule">
                        <label class="text-[11px] font-semibold text-slate-600">
                            Follow up
                            <input type="datetime-local" name="follow_up_at" class="mt-0.5 border border-slate-300 px-2 py-1 text-sm">
                        </label>
                        <button class="rounded-sm bg-slate-950 px-3 py-1.5 text-[11px] font-bold text-white">Schedule</button>
                    </form>
                    <form method="post" action="{{ route('operations.repair-orders.recommendations.follow-up', [$repairOrder, $row['id']]) }}" class="mt-1" x-show="follow" x-cloak>
                        @csrf
                        <input type="hidden" name="action" value="complete">
                        <button class="text-[11px] font-semibold text-slate-600 hover:text-slate-950">Mark follow-up done</button>
                    </form>

                    <form method="post" action="{{ route('operations.repair-orders.recommendations.resolve', [$repairOrder, $row['id']]) }}" class="mt-2 grid gap-2 border border-slate-200 bg-slate-50 p-2 sm:grid-cols-2" x-show="resolve" x-cloak>
                        @csrf
                        <label class="text-[11px] font-semibold text-slate-600">
                            Why resolved
                            <select name="resolved_reason" required class="mt-0.5 w-full border border-slate-300 px-2 py-1 text-sm">
                                @foreach ($workspace['resolved_reasons'] as $reason)
                                    <option value="{{ $reason['value'] }}">{{ $reason['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-[11px] font-semibold text-slate-600">
                            Note
                            <input name="note" class="mt-0.5 w-full border border-slate-300 px-2 py-1 text-sm font-normal">
                        </label>
                        <button class="rounded-sm bg-slate-950 px-3 py-1.5 text-[11px] font-bold text-white">Resolve</button>
                    </form>
                    <form method="post" action="{{ route('operations.repair-orders.recommendations.dismiss', [$repairOrder, $row['id']]) }}" class="mt-1" x-show="resolve" x-cloak>
                        @csrf
                        <input type="hidden" name="dismissal_reason" value="advisor">
                        <button class="text-[11px] font-semibold text-slate-500 hover:text-rose-800">Dismiss instead</button>
                    </form>
                @endif

                <ol class="mt-2 space-y-1 border-t border-slate-100 pt-2" x-show="open" x-cloak>
                    @forelse ($row['history'] as $event)
                        <li class="text-[11px] leading-4 text-slate-600">
                            <span class="font-semibold text-slate-800">{{ $event['label'] }}</span>
                            @if ($event['amount_cents'])
                                · {{ $money($event['amount_cents']) }}
                            @endif
                            @if ($event['reason'])
                                · {{ $event['reason'] }}
                            @endif
                            · {{ $event['occurred_at']?->timezone($displayTz)->format('M j, g:i A') }}
                            @if ($event['note'])
                                — {{ $event['note'] }}
                            @endif
                        </li>
                    @empty
                        <li class="text-[11px] text-slate-500">No presentation history yet. Not presented is not a decline.</li>
                    @endforelse
                </ol>
            </article>
        @endforeach
    </div>
</div>
