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

    $sentimentTone = $row['sentiment']
        ? \App\Ark\Operations\Telephony\CallSessionAnalysisProjection::sentimentToneClasses($row['sentiment'])
        : null;
@endphp

<div @class(['space-y-2', 'mt-2' => $compact, 'mt-0' => ! $compact])>
    @if ($showMeta)
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="rounded-sm border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">{{ $row['direction_label'] }}</span>
            @if ($row['coaching_follow_up_pinned'])
                <span class="rounded-sm border border-indigo-200 bg-indigo-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-900">Pinned follow-up</span>
            @endif
            <span class="rounded-sm border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">{{ $row['analysis_status_label'] }}</span>
        </div>
    @endif

    @php
        $pairLayout = $compact && $hasCustomerSignals && $hasAdvisorSignals;
    @endphp

    <div @class([
        'gap-2',
        'grid grid-cols-1 sm:grid-cols-2' => $pairLayout,
        'space-y-2' => ! $pairLayout,
    ])>
    @if ($hasCustomerSignals)
        <div class="rounded-sm border border-sky-200/90 bg-sky-50/70 px-2 py-1.5">
            <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-sky-900">Customer</p>
            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                @if ($row['sentiment'])
                    <span @class([
                        'rounded-sm border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                        $sentimentTone[0],
                        $sentimentTone[1],
                        $sentimentTone[2],
                    ])>{{ $row['sentiment_label'] ?? $row['sentiment'] }}</span>
                @endif
                @if ($row['customer_intent'])
                    <span
                        class="rounded-sm border border-sky-300 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-sky-950"
                        title="{{ $row['customer_intent'] }}"
                    >Intent · {{ $compact ? Str::limit($row['customer_intent'], 52) : Str::limit($row['customer_intent'], 88) }}</span>
                @endif
                @if ($row['outcome'])
                    <span
                        class="rounded-sm border border-sky-300 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-sky-950"
                        title="{{ $row['outcome'] }}"
                    >Outcome · {{ $compact ? Str::limit($row['outcome'], 40) : Str::limit($row['outcome'], 72) }}</span>
                @endif
                @if ($row['follow_up_needed'])
                    <span class="rounded-sm border border-amber-300 bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950">Needs follow-up</span>
                @endif
            </div>
        </div>
    @endif

    @if ($hasAdvisorSignals)
        <div class="rounded-sm border border-violet-200/90 bg-violet-50/60 px-2 py-1.5">
            <p class="text-[9px] font-bold uppercase tracking-[0.12em] text-violet-950">Advisor</p>
            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                @if ($row['empathy_score'])
                    @php($empathyTone = \App\Ark\Operations\Telephony\CallSessionAnalysisProjection::advisorScoreToneClasses((int) $row['empathy_score']))
                    <span @class([
                        'rounded-sm border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide tabular-nums',
                        $empathyTone[0],
                        $empathyTone[1],
                        $empathyTone[2],
                    ])>Empathy {{ $row['empathy_score'] }}/5{{ $row['empathy_label'] ? ' · '.$row['empathy_label'] : '' }}</span>
                @endif
                @if ($row['ownership_score'])
                    @php($ownershipTone = \App\Ark\Operations\Telephony\CallSessionAnalysisProjection::advisorScoreToneClasses((int) $row['ownership_score']))
                    <span @class([
                        'rounded-sm border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide tabular-nums',
                        $ownershipTone[0],
                        $ownershipTone[1],
                        $ownershipTone[2],
                    ])>Ownership {{ $row['ownership_score'] }}/5{{ $row['ownership_label'] ? ' · '.$row['ownership_label'] : '' }}</span>
                @endif
                @if ($row['clarity_score'])
                    @php($clarityTone = \App\Ark\Operations\Telephony\CallSessionAnalysisProjection::advisorScoreToneClasses((int) $row['clarity_score']))
                    <span @class([
                        'rounded-sm border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide tabular-nums',
                        $clarityTone[0],
                        $clarityTone[1],
                        $clarityTone[2],
                    ])>Clarity {{ $row['clarity_score'] }}/5{{ $row['clarity_label'] ? ' · '.$row['clarity_label'] : '' }}</span>
                @endif
                @if ($row['coaching_priority_label'] && $row['coaching_priority'] !== 'none')
                    <span @class([
                        'rounded-sm border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                        'border-rose-300 bg-rose-100 text-rose-950' => $row['coaching_priority'] === 'high',
                        'border-amber-300 bg-amber-100 text-amber-950' => $row['coaching_priority'] === 'medium',
                        'border-violet-300 bg-violet-100 text-violet-900' => $row['coaching_priority'] === 'low',
                    ])>{{ $row['coaching_priority_label'] }}</span>
                @endif
                @if ($row['missed_upsell'])
                    <span class="rounded-sm border border-rose-300 bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-950">Missed upsell</span>
                @endif
                @if ($row['appointment_captured'] !== null)
                    <span @class([
                        'rounded-sm border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                        'border-emerald-300 bg-emerald-100 text-emerald-950' => $row['appointment_captured'],
                        'border-amber-300 bg-amber-100 text-amber-950' => ! $row['appointment_captured'],
                    ])>Appointment {{ $row['appointment_captured'] ? 'captured' : 'missed' }}</span>
                @endif
            </div>
        </div>
    @endif
    </div>
</div>
