@php
    use App\Ark\Operations\LaborGuides\Rte\RepairTimeEngine;

    $rteReady = $rteLaborGuide['available'] ?? false;
    $rteBlockedReason = $rteLaborGuide['blocked_reason'] ?? RepairTimeEngine::NAME.' is unavailable for this repair order.';
@endphp

<button
    type="button"
    class="ops-review-action ops-review-action--labor-guide ops-review-action--labor-guide-rte"
    @click="openRteLaborGuide()"
    title="{{ $rteReady ? RepairTimeEngine::buttonTooltip() : $rteBlockedReason }}"
    @class(['opacity-60' => ! $rteReady])
>
    {{ RepairTimeEngine::NAME }}
</button>
