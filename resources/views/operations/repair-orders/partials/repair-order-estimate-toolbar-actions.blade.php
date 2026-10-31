@php
    use App\Ark\Operations\LaborGuides\LaborGuideConnections;
    use App\Ark\Operations\Parts\PartsCatalogConnections;
    use App\Ark\Runtime\Preferences\EstimateToolbarPreference;

    $mode = $mode ?? 'edit';
    $isTerminal = $isTerminal ?? false;
    if (! isset($partsCatalogs)) {
        $partsCatalogs = $mode === 'edit' && ! $isTerminal && isset($repairOrder)
            ? app(PartsCatalogConnections::class)->forRepairOrder($repairOrder, auth()->user())
            : [];
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
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3" role="toolbar" aria-label="Estimate tools" data-estimate-tools>
        <div class="d-flex flex-wrap align-items-start gap-2">
            @if ($showConcernStore)
                <button
                    type="button"
                    class="btn btn-primary"
                    @click="focusCreateScope()"
                >
                    + Add Work
                </button>
            @endif

            {{-- Provider buttons keep their legacy colors: a shop picks each parts catalog's color in settings. --}}
            @if ($showLaborGuide && $laborGuides !== [])
                <x-operations.worksheet-island class="d-inline-block w-auto">
                    @include('operations.repair-orders.partials.repair-order-labor-guide-intent-button', [
                        'laborGuides' => $laborGuides,
                        'laborGuideDefault' => $laborGuideDefault,
                    ])
                </x-operations.worksheet-island>
            @endif

            @if ($showProcurement)
                @if ($partsCatalogs !== [])
                    <x-operations.worksheet-island class="d-inline-block w-auto">
                        @include('operations.repair-orders.partials.repair-order-partstech-toolbar-group', [
                            'partsCatalogs' => $partsCatalogs,
                            'partsCatalogDefault' => $partsCatalogDefault,
                        ])
                    </x-operations.worksheet-island>
                @endif
                @if ($showCaptureDealerQuote)
                    <button
                        type="button"
                        class="btn"
                        @click="$dispatch('ark:dealer-quote-capture-open')"
                        title="Import a dealer quote PDF or pasted text onto this estimate"
                    >
                        Import
                    </button>
                @endif
            @endif
        </div>

        <button
            type="button"
            class="btn btn-ghost-secondary"
            :class="estimateContext === 'portal' ? 'active' : ''"
            :aria-pressed="estimateContext === 'portal'"
            @click="toggleEstimateContext('portal')"
        >
            Customer View
        </button>
    </div>
@endif
