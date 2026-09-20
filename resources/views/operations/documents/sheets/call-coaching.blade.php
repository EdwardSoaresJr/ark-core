<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Call Coaching · {{ $sheet['row']['display_phone'] }}</title>
    @include('operations.documents.sheets._layout-styles')
    <style>
        .coaching-meta {
            margin-top: 0.12in;
            padding: 0.1in 0.12in;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
        }

        .coaching-meta-title {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .coaching-meta-sub {
            margin-top: 0.04in;
            color: #475569;
            font-size: 10px;
        }

        .coaching-columns {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.1in;
            margin-top: 0.12in;
        }

        .coaching-band {
            border: 1px solid #cbd5e1;
            padding: 0.1in 0.12in;
            min-height: 0.85in;
        }

        .coaching-band--customer {
            background: #f0f9ff;
            border-color: #7dd3fc;
        }

        .coaching-band--advisor {
            background: #f5f3ff;
            border-color: #c4b5fd;
        }

        .coaching-band-label {
            color: #334155;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .coaching-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.05in;
            margin-top: 0.06in;
        }

        .coaching-pill {
            border: 1px solid #cbd5e1;
            background: #fff;
            padding: 0.03in 0.06in;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .coaching-pill--warn {
            border-color: #fdba74;
            background: #ffedd5;
            color: #9a3412;
        }

        .coaching-pill--bad {
            border-color: #fda4af;
            background: #ffe4e6;
            color: #9f1239;
        }

        .coaching-pill--good {
            border-color: #86efac;
            background: #dcfce7;
            color: #166534;
        }

        .coaching-body {
            margin-top: 0.05in;
            font-size: 10px;
            line-height: 1.5;
        }

        .coaching-section {
            margin-top: 0.12in;
        }

        .coaching-section-title {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.05in;
        }

        .coaching-list {
            margin: 0;
            padding-left: 0.16in;
        }

        .coaching-list li {
            margin-bottom: 0.04in;
        }

        .coaching-log {
            border-top: 1px solid #e2e8f0;
            padding-top: 0.06in;
            margin-top: 0.06in;
        }

        .coaching-log-meta {
            color: #64748b;
            font-size: 9px;
            font-weight: 700;
        }

        .coaching-notes-blank {
            margin-top: 0.12in;
            border: 1px dashed #cbd5e1;
            min-height: 0.75in;
            padding: 0.08in;
        }

        .coaching-footer {
            margin-top: 0.14in;
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    @php
        $row = $sheet['row'];
    @endphp
    <div class="sheet">
        @include('operations.documents.partials._pdf-shop-header', ['shop' => $sheet['shop']])

        <div class="sheet-context">
            <div>
                <p class="eyebrow">{{ $sheet['title'] }}</p>
                <h2 class="sheet-context-title">{{ $sheet['heading'] }}</h2>
            </div>
            <p class="sheet-context-meta">{{ $sheet['printed_at'] }}</p>
        </div>

        <div class="coaching-meta">
            <p class="coaching-meta-title">{{ $sheet['identity']['primary_line'] }}</p>
            @if ($sheet['identity']['secondary_line'])
                <p class="coaching-meta-sub">{{ $sheet['identity']['secondary_line'] }}</p>
            @endif
        </div>

        <div class="coaching-columns">
            <section class="coaching-band coaching-band--customer">
                <p class="coaching-band-label">Customer</p>
                <div class="coaching-pills">
                    @if ($row['sentiment'])
                        <span @class([
                            'coaching-pill',
                            'coaching-pill--good' => $row['sentiment'] === 'positive',
                            'coaching-pill--warn' => in_array($row['sentiment'], ['concerned', 'neutral'], true),
                            'coaching-pill--bad' => $row['sentiment'] === 'frustrated',
                        ])>{{ $row['sentiment_label'] ?? $row['sentiment'] }}</span>
                    @endif
                    @if ($row['follow_up_needed'])
                        <span class="coaching-pill coaching-pill--warn">Needs follow-up</span>
                    @endif
                </div>
                @if ($row['customer_intent'])
                    <p class="coaching-body"><strong>Intent:</strong> {{ $row['customer_intent'] }}</p>
                @endif
                @if ($row['outcome'])
                    <p class="coaching-body"><strong>Outcome:</strong> {{ $row['outcome'] }}</p>
                @endif
                @if ($row['follow_up_notes'])
                    <p class="coaching-body"><strong>Follow-up:</strong> {{ $row['follow_up_notes'] }}</p>
                @endif
            </section>

            <section class="coaching-band coaching-band--advisor">
                <p class="coaching-band-label">Advisor</p>
                <div class="coaching-pills">
                    @if ($row['empathy_score'])
                        <span @class([
                            'coaching-pill',
                            'coaching-pill--good' => $row['empathy_score'] >= 4,
                            'coaching-pill--warn' => $row['empathy_score'] === 3,
                            'coaching-pill--bad' => $row['empathy_score'] <= 2,
                        ])>Empathy {{ $row['empathy_score'] }}/5</span>
                    @endif
                    @if ($row['ownership_score'])
                        <span @class([
                            'coaching-pill',
                            'coaching-pill--good' => $row['ownership_score'] >= 4,
                            'coaching-pill--warn' => $row['ownership_score'] === 3,
                            'coaching-pill--bad' => $row['ownership_score'] <= 2,
                        ])>Ownership {{ $row['ownership_score'] }}/5</span>
                    @endif
                    @if ($row['clarity_score'])
                        <span @class([
                            'coaching-pill',
                            'coaching-pill--good' => $row['clarity_score'] >= 4,
                            'coaching-pill--warn' => $row['clarity_score'] === 3,
                            'coaching-pill--bad' => $row['clarity_score'] <= 2,
                        ])>Clarity {{ $row['clarity_score'] }}/5</span>
                    @endif
                    @if ($row['coaching_priority_label'] && $row['coaching_priority'] !== 'none')
                        <span class="coaching-pill coaching-pill--warn">{{ $row['coaching_priority_label'] }}</span>
                    @endif
                    @if ($row['missed_upsell'])
                        <span class="coaching-pill coaching-pill--bad">Missed upsell</span>
                    @endif
                    @if ($row['appointment_captured'] !== null)
                        <span @class([
                            'coaching-pill',
                            'coaching-pill--good' => $row['appointment_captured'],
                            'coaching-pill--warn' => ! $row['appointment_captured'],
                        ])>Appointment {{ $row['appointment_captured'] ? 'captured' : 'missed' }}</span>
                    @endif
                </div>
                @if ($row['empathy_notes'])
                    <p class="coaching-body"><strong>Empathy:</strong> {{ $row['empathy_notes'] }}</p>
                @endif
                @if ($row['missed_upsell_notes'])
                    <p class="coaching-body"><strong>Missed upsell:</strong> {{ $row['missed_upsell_notes'] }}</p>
                @endif
            </section>
        </div>

        @if ($row['summary'])
            <section class="coaching-section">
                <p class="coaching-section-title">Call summary</p>
                <p>{{ $row['summary'] }}</p>
            </section>
        @endif

        @if (! empty($row['coaching_strengths']) || ! empty($row['coaching_improvements']) || $row['coaching_notes'])
            <section class="coaching-section">
                <p class="coaching-section-title">Coaching focus</p>
                @if ($row['coaching_notes'])
                    <p class="coaching-body">{{ $row['coaching_notes'] }}</p>
                @endif
                @if (! empty($row['coaching_strengths']))
                    <p class="coaching-body"><strong>Strengths</strong></p>
                    <ul class="coaching-list">
                        @foreach ($row['coaching_strengths'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (! empty($row['coaching_improvements']))
                    <p class="coaching-body"><strong>Improve next time</strong></p>
                    <ul class="coaching-list">
                        @foreach ($row['coaching_improvements'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if (count($sheet['coaching_logs']) > 0)
            <section class="coaching-section">
                <p class="coaching-section-title">Owner debrief notes</p>
                @foreach ($sheet['coaching_logs'] as $log)
                    <div class="coaching-log">
                        <p class="coaching-log-meta">
                            {{ $log['discussed_at_label'] }}
                            · {{ $log['staff_name'] }}
                            @if ($log['recorded_by_name'])
                                · logged by {{ $log['recorded_by_name'] }}
                            @endif
                        </p>
                        <p class="coaching-body">{{ $log['notes'] }}</p>
                    </div>
                @endforeach
            </section>
        @endif

        <section class="coaching-section">
            <p class="coaching-section-title">In-person coaching notes</p>
            <div class="coaching-notes-blank"></div>
        </section>

        <p class="coaching-footer">Staff coaching handout — not for customer distribution</p>
    </div>
</body>
</html>
