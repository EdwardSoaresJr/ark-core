@props(['row', 'compact' => false])

@include('operations.owner.partials.call-intelligence-signal-groups', [
    'row' => $row,
    'compact' => $compact,
    'showMeta' => true,
])

@if (! $compact && ($row['empathy_notes'] || $row['follow_up_notes'] || $row['missed_upsell_notes'] || $row['appointment_notes'] || $row['coaching_notes'] || ! empty($row['coaching_strengths']) || ! empty($row['coaching_improvements'])))
    <div class="row g-3 mt-0" data-call-details>
        @if ($row['customer_intent'] || $row['outcome'] || $row['follow_up_notes'])
            <div class="col-12">
                <div class="card card-sm" data-call-details-customer>
                    <div class="card-status-start bg-azure"></div>
                    <div class="card-body">
                        <div class="subheader text-azure">Customer detail</div>
                        <dl class="row g-2 small mt-1 mb-0">
                            @if ($row['customer_intent'])
                                <div class="col-12">
                                    <dt class="text-secondary text-uppercase">Intent</dt>
                                    <dd class="mb-0">{{ $row['customer_intent'] }}</dd>
                                </div>
                            @endif
                            @if ($row['outcome'])
                                <div class="col-12 col-sm-6">
                                    <dt class="text-secondary text-uppercase">Outcome</dt>
                                    <dd class="mb-0">{{ $row['outcome'] }}</dd>
                                </div>
                            @endif
                            @if ($row['follow_up_notes'])
                                <div class="col-12">
                                    <dt class="text-warning text-uppercase">Follow-up notes</dt>
                                    <dd class="mb-0">{{ $row['follow_up_notes'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
        @endif

        @if ($row['empathy_notes'] || $row['missed_upsell_notes'] || $row['appointment_notes'] || $row['coaching_notes'] || ! empty($row['coaching_strengths']) || ! empty($row['coaching_improvements']))
            <div class="col-12">
                <div class="card card-sm" data-call-details-advisor>
                    <div class="card-status-start bg-purple"></div>
                    <div class="card-body">
                        <div class="subheader text-purple">Advisor detail</div>
                        <dl class="row g-2 small mt-1 mb-0">
                            @if ($row['empathy_notes'])
                                <div class="col-12">
                                    <dt class="text-secondary text-uppercase">Empathy evidence</dt>
                                    <dd class="mb-0">{{ $row['empathy_notes'] }}</dd>
                                </div>
                            @endif
                            @if ($row['missed_upsell_notes'])
                                <div class="col-12">
                                    <dt class="text-danger text-uppercase">Missed upsell</dt>
                                    <dd class="mb-0">{{ $row['missed_upsell_notes'] }}</dd>
                                </div>
                            @endif
                            @if ($row['appointment_notes'])
                                <div class="col-12">
                                    <dt class="text-secondary text-uppercase">Appointment</dt>
                                    <dd class="mb-0">{{ $row['appointment_notes'] }}</dd>
                                </div>
                            @endif
                            @if ($row['coaching_notes'])
                                <div class="col-12">
                                    <dt class="text-secondary text-uppercase">Coaching summary</dt>
                                    <dd class="mb-0">{{ $row['coaching_notes'] }}</dd>
                                </div>
                            @endif
                            @if (! empty($row['coaching_strengths']))
                                <div class="col-12 col-sm-6">
                                    <dt class="text-green text-uppercase">Strengths</dt>
                                    <dd class="mb-0">
                                        <ul class="ps-3 mb-0" style="list-style: disc">
                                            @foreach ($row['coaching_strengths'] as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </dd>
                                </div>
                            @endif
                            @if (! empty($row['coaching_improvements']))
                                <div class="col-12 col-sm-6">
                                    <dt class="text-warning text-uppercase">Improve next time</dt>
                                    <dd class="mb-0">
                                        <ul class="ps-3 mb-0" style="list-style: disc">
                                            @foreach ($row['coaching_improvements'] as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif

@if (! $compact && filled($row['suggested_reply'] ?? null))
    <div class="card card-sm mt-3" data-call-suggested-reply>
        <div class="card-status-start bg-green"></div>
        <div class="card-body">
            <div class="subheader text-green">{{ ($row['kind'] ?? 'call') === 'sms' ? 'Suggested reply' : 'Suggested note' }}</div>
            <p class="mt-1 mb-0">{{ $row['suggested_reply'] }}</p>
        </div>
    </div>
@endif
