@php
    use App\Ark\Operations\LaborGuides\LaborGuideIntent;

    $rteReady = $rteLaborGuide['available'] ?? false;
    $rteBlockedReason = $rteLaborGuide['blocked_reason'] ?? 'Labor times are unavailable for this repair order.';
@endphp

<button
    type="button"
    class="ops-review-action ops-review-action--labor-guide ops-review-action--labor-guide-rte"
    @click="openRteLaborGuide()"
    title="{{ $rteReady ? LaborGuideIntent::tooltip() : $rteBlockedReason }}"
    @class(['opacity-60' => ! $rteReady])
>
    {{ LaborGuideIntent::label() }}
</button>
