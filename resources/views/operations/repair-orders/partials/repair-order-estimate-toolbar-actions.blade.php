@php
    use App\Ark\Operations\LaborGuides\LaborGuideProvider;
    use App\Ark\Operations\LaborGuides\Rte\RteLaborGuideAvailability;

    $mode = $mode ?? 'edit';
    $isTerminal = $isTerminal ?? false;
    $laborGuideConcernId = $laborGuideConcernId ?? null;
    $showConcernStore = ($showConcernStore ?? false) && $mode === 'edit' && ! $isTerminal;
    $showCaptureDealerQuote = $mode === 'edit' && ! $isTerminal;
    $showLaborGuides = $mode === 'edit' && ! $isTerminal && LaborGuideProvider::enabled() !== [];
    $rteDataAvailable = app(RteLaborGuideAvailability::class)->available();
    $showRteLaborGuide = $mode === 'edit' && ! $isTerminal && $rteDataAvailable;
    $showProcurement = $showCaptureDealerQuote;
    $showLaborGroup = $showLaborGuides || $showRteLaborGuide;
@endphp

@if ($showConcernStore || $showProcurement || $showLaborGroup)
    <div class="ops-review-toolbar-section ops-review-toolbar-section--leading">
        <div class="ops-review-toolbar-row ops-estimate-toolbar-row">
            @if ($showConcernStore)
                <div class="ops-review-toolbar-group" data-toolbar-group="scope">
                    <button
                        type="button"
                        class="ops-review-action ops-review-action--primary"
                        @click="focusCreateScope()"
                    >
                        + Add Work
                    </button>
                </div>
            @endif

            @if ($showProcurement)
                <div class="ops-review-toolbar-group" data-toolbar-group="procurement">
                    @if ($showCaptureDealerQuote)
                        <button
                            type="button"
                            class="ops-review-action ops-review-action--procurement"
                            @click="$dispatch('ark:dealer-quote-capture-open')"
                            title="Import a dealer quote PDF or pasted text onto this estimate"
                        >
                            Import Quote
                        </button>
                    @endif
                </div>
            @endif

            @if ($showLaborGroup)
                <div class="ops-review-toolbar-group" data-toolbar-group="labor-guides">
                    @if ($showLaborGuides)
                        @include('operations.repair-orders.partials.repair-order-labor-guide-toolbar-buttons', [
                            'repairOrder' => $repairOrder,
                            'concernId' => $laborGuideConcernId,
                        ])
                    @endif

                    @if ($showRteLaborGuide)
                        @include('operations.repair-orders.partials.repair-order-rte-labor-toolbar-button', [
                            'rteLaborGuide' => $rteLaborGuide,
                        ])
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif
