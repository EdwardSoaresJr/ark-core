@php
    use App\Ark\Operations\LaborGuides\LaborGuideConnections;
    use App\Ark\Operations\Parts\PartsCatalogConnections;
    use App\Ark\Runtime\Preferences\EstimateToolbarPreference;

    $mode = $mode ?? 'edit';
    $isTerminal = $isTerminal ?? false;
    $partsCatalogs = $partsCatalogs ?? [];

    if ($partsCatalogs === [] && $mode === 'edit' && ! $isTerminal && isset($repairOrder)) {
        $partsCatalogs = app(PartsCatalogConnections::class)->forRepairOrder($repairOrder, auth()->user());
    } elseif ($mode !== 'edit' || $isTerminal) {
        $partsCatalogs = [];
    }

    $laborGuideConcernId = $laborGuideConcernId ?? null;
    $laborGuides = $laborGuides ?? [];
    $showConcernStore = ($showConcernStore ?? false) && $mode === 'edit' && ! $isTerminal;
    $showCaptureDealerQuote = $mode === 'edit' && ! $isTerminal;
    $showLaborGuide = $mode === 'edit' && ! $isTerminal;

    if ($showLaborGuide && $laborGuides === [] && isset($repairOrder)) {
        $laborGuides = app(LaborGuideConnections::class)->forRepairOrder(
            $repairOrder,
            $laborGuideConcernId,
            $rteLaborGuide ?? [],
        );
    } elseif (! $showLaborGuide) {
        $laborGuides = [];
    }

    $partsCatalogDefault = $partsCatalogDefault
        ?? EstimateToolbarPreference::resolve(
            auth()->user(),
            EstimateToolbarPreference::KIND_PARTS,
            collect($partsCatalogs)
                ->filter(fn (array $catalog): bool => ($catalog['mode'] ?? 'catalog') === 'catalog' && $catalog['can_open'])
                ->pluck('key')
                ->all()
                ?: collect($partsCatalogs)->filter(fn (array $catalog): bool => $catalog['can_open'])->pluck('key')->all()
                ?: array_column($partsCatalogs, 'key'),
        );
    $laborGuideDefault = $laborGuideDefault
        ?? EstimateToolbarPreference::resolve(auth()->user(), EstimateToolbarPreference::KIND_LABOR, array_column($laborGuides, 'key'));
    $showProcurement = $partsCatalogs !== [] || $showCaptureDealerQuote;
@endphp

@if ($showConcernStore || $showProcurement || $showLaborGuide)
    <div class="ops-estimate-build-toolbar" aria-label="Estimate tools">
        <div class="ops-estimate-build-toolbar__build">
            @if ($showConcernStore)
                <button
                    type="button"
                    class="ops-review-action ops-review-action--primary"
                    @click="focusCreateScope()"
                >
                    + Add Work
                </button>
            @endif

            @if ($showLaborGuide)
                @include('operations.repair-orders.partials.repair-order-labor-guide-intent-button', [
                    'laborGuides' => $laborGuides,
                    'laborGuideDefault' => $laborGuideDefault,
                ])
            @endif

            @if ($showProcurement)
                @include('operations.repair-orders.partials.repair-order-partstech-toolbar-group', [
                    'partsCatalogs' => $partsCatalogs,
                    'partsCatalogDefault' => $partsCatalogDefault,
                ])
                @if ($showCaptureDealerQuote)
                    <button
                        type="button"
                        class="ops-review-action ops-review-action--procurement"
                        @click="$dispatch('ark:dealer-quote-capture-open')"
                        title="Import a dealer quote PDF or pasted text onto this estimate"
                    >
                        Import
                    </button>
                @endif
            @endif
        </div>

        <div class="ops-estimate-build-toolbar__customer">
            <button
                type="button"
                class="ops-estimate-build-toolbar__quiet"
                :class="estimateContext === 'portal' ? 'is-active' : ''"
                @click="toggleEstimateContext('portal')"
            >
                Customer View
            </button>
        </div>
    </div>
@endif
