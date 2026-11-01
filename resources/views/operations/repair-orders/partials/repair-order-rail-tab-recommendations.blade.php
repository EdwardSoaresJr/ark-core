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
    $money = fn (?int $cents): string => $cents ? '$'.number_format($cents / 100, 2) : '-';
    $when = fn ($value) => $value?->timezone($displayTz)->format('M j, Y') ?? '-';
@endphp

<div id="recommendations-rail" class="d-flex flex-column gap-3" data-recommendations-panel>
    <div>
        <h3 class="card-title mb-1">What this vehicle still needs</h3>
        @if ($workspace['open_count'] > 0 && ($workspace['safety_count'] > 0 || $workspace['follow_up_due_count'] > 0 || $workspace['estimated_cents'] > 0 || $workspace['current_mileage']))
            <p class="small text-secondary mb-0">
                @if ($workspace['safety_count'] > 0)
                    <span class="fw-bold text-danger">{{ $workspace['safety_count'] }} safety</span>
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
        <p class="text-secondary mb-0">No open recommendations for this vehicle.</p>
    @endif

    @if ($canManage && ! $isTerminal)
        <form
            method="post"
            action="{{ route('operations.repair-orders.recommendations.store', $repairOrder) }}"
            class="card card-sm"
            data-recommendation-create
            x-data="{ open: false, notes: false, dueKind: 'now' }"
        >
            @csrf
            <div class="card-body py-2">
                <button type="button" class="btn btn-sm" :aria-expanded="open" @click="open = ! open">
                    + Record recommendation
                </button>
            </div>
            <div class="card-body border-top" x-show="open" x-cloak>
                <div class="mb-3">
                    <label class="form-label" for="recommendation-title-{{ $repairOrder->repair_order_id }}">Recommendation</label>
                    <input id="recommendation-title-{{ $repairOrder->repair_order_id }}" name="title" required maxlength="180" placeholder="Replace front brake pads &amp; rotors" class="form-control">
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md">
                        <label class="form-label" for="recommendation-urgency-{{ $repairOrder->repair_order_id }}">Urgency</label>
                        <select id="recommendation-urgency-{{ $repairOrder->repair_order_id }}" name="urgency" class="form-select">
                            @foreach ($workspace['urgencies'] as $urgency)
                                <option value="{{ $urgency['value'] }}">{{ $urgency['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md">
                        <label class="form-label" for="recommendation-due-kind-{{ $repairOrder->repair_order_id }}">Due</label>
                        <select id="recommendation-due-kind-{{ $repairOrder->repair_order_id }}" name="due_kind" x-model="dueKind" class="form-select">
                            @foreach ($workspace['due_kinds'] as $kind)
                                <option value="{{ $kind['value'] }}" @selected($kind['value'] === 'now')>{{ $kind['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md" x-show="dueKind === 'date' || dueKind === 'date_or_mileage'" x-cloak>
                        <label class="form-label" for="recommendation-due-on-{{ $repairOrder->repair_order_id }}">Date</label>
                        <input id="recommendation-due-on-{{ $repairOrder->repair_order_id }}" type="date" name="due_on" class="form-control" x-bind:disabled="dueKind !== 'date' && dueKind !== 'date_or_mileage'">
                    </div>
                    <div class="col-6 col-md" x-show="dueKind === 'mileage' || dueKind === 'date_or_mileage'" x-cloak>
                        <label class="form-label" for="recommendation-due-mileage-{{ $repairOrder->repair_order_id }}">Mileage</label>
                        <input id="recommendation-due-mileage-{{ $repairOrder->repair_order_id }}" type="number" name="due_mileage" min="0" class="form-control" x-bind:disabled="dueKind !== 'mileage' && dueKind !== 'date_or_mileage'">
                    </div>
                    <div class="col-auto">
                        <label class="form-check mb-2">
                            <input type="checkbox" name="safety_related" value="1" class="form-check-input">
                            <span class="form-check-label">Safety</span>
                        </label>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </div>
                <button type="button" class="btn btn-link btn-sm px-0 mt-2" @click="notes = ! notes">
                    <span x-show="! notes">Add notes</span>
                    <span x-show="notes" x-cloak>Hide notes</span>
                </button>
                <div x-show="notes" x-cloak class="mt-2">
                    <label class="form-label" for="recommendation-customer-description-{{ $repairOrder->repair_order_id }}">Customer description</label>
                    <textarea id="recommendation-customer-description-{{ $repairOrder->repair_order_id }}" name="customer_description" rows="2" class="form-control"></textarea>
                </div>
            </div>
        </form>
    @endif

    @if (collect($workspace['recommendations'])->isNotEmpty())
        <div class="card card-sm">
            <div class="list-group list-group-flush">
                @foreach ($workspace['recommendations'] as $row)
                    <article
                        class="list-group-item"
                        data-recommendation-row="{{ $row['id'] }}"
                        x-data="{ open: false, decide: false, follow: false, resolve: false }"
                    >
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div style="min-width: 0">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <h4 class="mb-0">{{ $row['title'] }}</h4>
                                    @if ($row['safety_related'])
                                        <span class="badge bg-red-lt">Safety</span>
                                    @endif
                                    @if ($row['service_due'])
                                        <span class="badge bg-yellow-lt">Due</span>
                                    @endif
                                    @if ($row['on_current_estimate'])
                                        <span class="badge bg-green-lt">On estimate</span>
                                    @endif
                                </div>
                                <div class="small text-secondary">
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
                                        - {{ $row['last_decision_reason'] }}
                                    @endif
                                    · {{ $money($row['estimated_cents']) }}
                                </div>
                                @if ($row['follow_up_at'])
                                    <div @class(['small', 'fw-bold text-orange' => $row['follow_up_due'], 'text-secondary' => ! $row['follow_up_due']])>
                                        Follow up {{ $row['follow_up_at']->timezone($displayTz)->format('M j, g:i A') }}
                                        @if ($row['follow_up_owner_name'])
                                            · {{ $row['follow_up_owner_name'] }}
                                        @endif
                                    </div>
                                @endif
                                @if ($row['source_finding_label'])
                                    <div class="small text-secondary">Inspection: {{ $row['source_finding_label'] }}</div>
                                @endif
                            </div>
                            <div class="btn-list">
                                @if ($canManage && ! $isTerminal && ! $row['on_current_estimate'])
                                    <form method="post" action="{{ route('operations.repair-orders.recommendations.add-to-estimate', [$repairOrder, $row['id']]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm">Add to Estimate</button>
                                    </form>
                                @endif
                                @if ($canManage && ! $isTerminal)
                                    <form method="post" action="{{ route('operations.repair-orders.recommendations.present', [$repairOrder, $row['id']]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm">Present</button>
                                    </form>
                                    <button type="button" class="btn btn-sm" :aria-expanded="decide" @click="decide = ! decide">Decision</button>
                                    <button type="button" class="btn btn-sm" :aria-expanded="follow" @click="follow = ! follow">Follow-up</button>
                                    <button type="button" class="btn btn-sm" :aria-expanded="resolve" @click="resolve = ! resolve">Resolve</button>
                                @endif
                                <button type="button" class="btn btn-sm btn-ghost-secondary" :aria-expanded="open" @click="open = ! open">History</button>
                            </div>
                        </div>

                        @if ($canManage && ! $isTerminal)
                            <form method="post" action="{{ route('operations.repair-orders.recommendations.decision', [$repairOrder, $row['id']]) }}" class="card card-sm mt-2" x-show="decide" x-cloak>
                                @csrf
                                <div class="card-body">
                                    <div class="row g-2">
                                        <div class="col-12 col-sm-4">
                                            <label class="form-label" for="recommendation-decision-{{ $row['id'] }}">Decision</label>
                                            <select id="recommendation-decision-{{ $row['id'] }}" name="decision" class="form-select form-select-sm">
                                                <option value="approved">Authorized</option>
                                                <option value="declined">Declined</option>
                                                <option value="deferred">Deferred</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-sm-4">
                                            <label class="form-label" for="recommendation-reason-{{ $row['id'] }}">Reason</label>
                                            <select id="recommendation-reason-{{ $row['id'] }}" name="reason_code" class="form-select form-select-sm">
                                                <option value="">Optional</option>
                                                @foreach ($workspace['decision_reasons'] as $reason)
                                                    <option value="{{ $reason['value'] }}">{{ $reason['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-sm-4">
                                            <label class="form-label" for="recommendation-decision-follow-up-{{ $row['id'] }}">Follow up</label>
                                            <input id="recommendation-decision-follow-up-{{ $row['id'] }}" type="datetime-local" name="follow_up_at" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="recommendation-decision-note-{{ $row['id'] }}">Note</label>
                                            <input id="recommendation-decision-note-{{ $row['id'] }}" name="note" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary mt-2">Record</button>
                                </div>
                            </form>

                            <form method="post" action="{{ route('operations.repair-orders.recommendations.follow-up', [$repairOrder, $row['id']]) }}" class="card card-sm mt-2" x-show="follow" x-cloak>
                                @csrf
                                <input type="hidden" name="action" value="schedule">
                                <div class="card-body">
                                    <div class="row g-2 align-items-end">
                                        <div class="col">
                                            <label class="form-label" for="recommendation-follow-up-{{ $row['id'] }}">Follow up</label>
                                            <input id="recommendation-follow-up-{{ $row['id'] }}" type="datetime-local" name="follow_up_at" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-sm btn-primary">Schedule</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <form method="post" action="{{ route('operations.repair-orders.recommendations.follow-up', [$repairOrder, $row['id']]) }}" class="mt-1" x-show="follow" x-cloak>
                                @csrf
                                <input type="hidden" name="action" value="complete">
                                <button type="submit" class="btn btn-link btn-sm px-0">Mark follow-up done</button>
                            </form>

                            <form method="post" action="{{ route('operations.repair-orders.recommendations.resolve', [$repairOrder, $row['id']]) }}" class="card card-sm mt-2" x-show="resolve" x-cloak>
                                @csrf
                                <div class="card-body">
                                    <div class="row g-2">
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label" for="recommendation-resolved-reason-{{ $row['id'] }}">Why resolved</label>
                                            <select id="recommendation-resolved-reason-{{ $row['id'] }}" name="resolved_reason" required class="form-select form-select-sm">
                                                @foreach ($workspace['resolved_reasons'] as $reason)
                                                    <option value="{{ $reason['value'] }}">{{ $reason['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label" for="recommendation-resolve-note-{{ $row['id'] }}">Note</label>
                                            <input id="recommendation-resolve-note-{{ $row['id'] }}" name="note" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary mt-2">Resolve</button>
                                </div>
                            </form>
                            <form method="post" action="{{ route('operations.repair-orders.recommendations.dismiss', [$repairOrder, $row['id']]) }}" class="mt-1" x-show="resolve" x-cloak>
                                @csrf
                                <input type="hidden" name="dismissal_reason" value="advisor">
                                <button type="submit" class="btn btn-link btn-sm px-0 text-danger">Dismiss instead</button>
                            </form>
                        @endif

                        <ol class="list-unstyled small border-top pt-2 mt-2 mb-0" x-show="open" x-cloak>
                            @forelse ($row['history'] as $event)
                                <li class="text-secondary">
                                    <span class="fw-bold text-body">{{ $event['label'] }}</span>
                                    @if ($event['amount_cents'])
                                        · {{ $money($event['amount_cents']) }}
                                    @endif
                                    @if ($event['reason'])
                                        · {{ $event['reason'] }}
                                    @endif
                                    · {{ $event['occurred_at']?->timezone($displayTz)->format('M j, g:i A') }}
                                    @if ($event['note'])
                                        - {{ $event['note'] }}
                                    @endif
                                </li>
                            @empty
                                <li class="text-secondary">No presentation history yet. Not presented is not a decline.</li>
                            @endforelse
                        </ol>
                    </article>
                @endforeach
            </div>
        </div>
    @endif
</div>
