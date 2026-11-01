@props(['row', 'compact' => false, 'showMeta' => true])

@php
    $hasCustomerSignals = filled($row['sentiment'])
        || filled($row['customer_intent'])
        || filled($row['outcome'])
        || $row['follow_up_needed'];

    $hasAdvisorSignals = filled($row['empathy_score'])
        || filled($row['ownership_score'])
        || filled($row['clarity_score'])
        || ($row['coaching_priority_label'] && $row['coaching_priority'] !== 'none')
        || $row['missed_upsell']
        || $row['appointment_captured'] !== null;

    // The projection's tone classes are the shared palette; map their first (background) class to a Tabler color.
    $tablerTone = fn (array $toneClasses): string => match ($toneClasses[0] ?? null) {
        'bg-emerald-100' => 'green',
        'bg-amber-100' => 'yellow',
        'bg-rose-100' => 'red',
        default => 'secondary',
    };

    $sentimentColor = $row['sentiment']
        ? $tablerTone(\App\Ark\Operations\Telephony\CallSessionAnalysisProjection::sentimentToneClasses($row['sentiment']))
        : null;
    $scoreColor = fn ($score): string => $tablerTone(\App\Ark\Operations\Telephony\CallSessionAnalysisProjection::advisorScoreToneClasses((int) $score));
    $pairLayout = $compact && $hasCustomerSignals && $hasAdvisorSignals;
@endphp

<div @class(['mt-2' => $compact]) data-call-signals>
    @if ($showMeta)
        <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
            <span class="badge bg-secondary-lt">{{ $row['direction_label'] }}</span>
            @if ($row['coaching_follow_up_pinned'])
                <span class="badge bg-indigo-lt">Pinned follow-up</span>
            @endif
            <span class="badge badge-outline text-secondary">{{ $row['analysis_status_label'] }}</span>
        </div>
    @endif

    @if ($hasCustomerSignals || $hasAdvisorSignals)
        <div class="row g-2">
            @if ($hasCustomerSignals)
                <div @class(['col-12', 'col-sm-6' => $pairLayout])>
                    <div class="border rounded p-2 h-100" data-call-signals-customer>
                        <div class="subheader text-azure">Customer</div>
                        <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                            @if ($row['sentiment'])
                                <span class="badge bg-{{ $sentimentColor }}-lt text-uppercase">{{ $row['sentiment_label'] ?? $row['sentiment'] }}</span>
                            @endif
                            @if ($row['customer_intent'])
                                <span class="badge bg-azure-lt text-wrap text-start" title="{{ $row['customer_intent'] }}">Intent · {{ $compact ? Str::limit($row['customer_intent'], 52) : Str::limit($row['customer_intent'], 88) }}</span>
                            @endif
                            @if ($row['outcome'])
                                <span class="badge bg-azure-lt text-wrap text-start" title="{{ $row['outcome'] }}">Outcome · {{ $compact ? Str::limit($row['outcome'], 40) : Str::limit($row['outcome'], 72) }}</span>
                            @endif
                            @if ($row['follow_up_needed'])
                                <span class="badge bg-yellow-lt text-uppercase">Needs follow-up</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if ($hasAdvisorSignals)
                <div @class(['col-12', 'col-sm-6' => $pairLayout])>
                    <div class="border rounded p-2 h-100" data-call-signals-advisor>
                        <div class="subheader text-purple">Advisor</div>
                        <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                            @if ($row['empathy_score'])
                                <span class="badge bg-{{ $scoreColor($row['empathy_score']) }}-lt text-wrap text-start">Empathy {{ $row['empathy_score'] }}/5{{ $row['empathy_label'] ? ' · '.$row['empathy_label'] : '' }}</span>
                            @endif
                            @if ($row['ownership_score'])
                                <span class="badge bg-{{ $scoreColor($row['ownership_score']) }}-lt text-wrap text-start">Ownership {{ $row['ownership_score'] }}/5{{ $row['ownership_label'] ? ' · '.$row['ownership_label'] : '' }}</span>
                            @endif
                            @if ($row['clarity_score'])
                                <span class="badge bg-{{ $scoreColor($row['clarity_score']) }}-lt text-wrap text-start">Clarity {{ $row['clarity_score'] }}/5{{ $row['clarity_label'] ? ' · '.$row['clarity_label'] : '' }}</span>
                            @endif
                            @if ($row['coaching_priority_label'] && $row['coaching_priority'] !== 'none')
                                <span @class([
                                    'badge text-uppercase',
                                    'bg-red-lt' => $row['coaching_priority'] === 'high',
                                    'bg-yellow-lt' => $row['coaching_priority'] === 'medium',
                                    'bg-purple-lt' => $row['coaching_priority'] === 'low',
                                ])>{{ $row['coaching_priority_label'] }}</span>
                            @endif
                            @if ($row['missed_upsell'])
                                <span class="badge bg-red-lt text-uppercase">Missed upsell</span>
                            @endif
                            @if ($row['appointment_captured'] !== null)
                                <span @class([
                                    'badge text-uppercase',
                                    'bg-green-lt' => $row['appointment_captured'],
                                    'bg-yellow-lt' => ! $row['appointment_captured'],
                                ])>Appointment {{ $row['appointment_captured'] ? 'captured' : 'missed' }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
