@php
    $authorViaModal = (bool) ($authorViaModal ?? false);
    $scopePositionLoop = $loop;
@endphp
<div class="d-flex flex-wrap flex-fill align-items-center justify-content-between gap-2" data-scope-settings>
    <div class="d-flex flex-wrap align-items-center gap-2" data-concern-status-controls>
        @if ($concern->shouldSurfaceRecommendationStatus())
            @include('operations.repair-orders.partials.repair-order-concern-recommendation-intent-control', [
                'repairOrder' => $repairOrder,
                'concern' => $concern,
                'isTerminal' => $isTerminal,
                'estimateVersion' => $estimateVersion,
            ])
        @endif
        {{-- A decision save refreshes this region; Deferred and Declined take the scope out of production. --}}
        <div id="concern-disposition-{{ $concern->id }}" class="d-flex flex-wrap align-items-center gap-2" data-continuity-region="concern-authorization">
            @include('operations.repair-orders.partials.repair-order-concern-disposition-control', [
                'repairOrder' => $repairOrder,
                'concern' => $concern,
                'isTerminal' => $isTerminal,
                'estimateVersion' => $estimateVersion,
            ])
            @include('operations.repair-orders.partials.repair-order-concern-production-status-control', [
                'repairOrder' => $repairOrder,
                'concern' => $concern,
                'isTerminal' => $isTerminal,
                'estimateVersion' => $estimateVersion,
            ])
        </div>
        @include('operations.repair-orders.partials.repair-order-concern-billing-control', [
            'repairOrder' => $repairOrder,
            'concern' => $concern,
            'isTerminal' => $isTerminal,
            'estimateVersion' => $estimateVersion,
            'laborRate' => $concernDefaultLaborRate ?? $laborRate ?? null,
            'authorViaModal' => $authorViaModal,
        ])
    </div>
    <div class="d-flex flex-wrap align-items-center gap-1">
        @if (($partsCatalogs ?? []) !== [])
            @php
                $selectedPartsCatalog = collect($partsCatalogs)->firstWhere('key', $partsCatalogDefault ?? null)
                    ?? ($partsCatalogs[0] ?? null);
            @endphp
            <button
                type="button"
                class="btn btn-sm btn-ghost-azure"
                data-scope-parts-catalog
                @click="openPartsCatalog(partsCatalogSelected()?.key, false, {{ $concern->id }})"
                :disabled="partsCatalogSelected()?.kind === 'partstech'
                    ? (partstechCatalogOpening || partstechPullLoading || ! partsCatalogSelected()?.can_open)
                    : ! partsCatalogSelected()?.can_open"
                :title="partsCatalogSelected()
                    ? ('Open ' + partsCatalogSelected().label + ' for this scope.')
                    : ''"
            >
                <span x-text="partsCatalogSelected()?.label">{{ $selectedPartsCatalog['label'] ?? '' }}</span>
            </button>
        @endif
        <form method="POST" action="{{ route('operations.repair-orders.concerns.move', [$repairOrder, $concern]) }}" data-refresh-scope="worksheet" data-continuity-focus="#concern-{{ $concern->id }} button[name='move-up']" @submit.prevent="submitWorksheetForm($event)">
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <input type="hidden" name="direction" value="up">
            <button type="submit" name="move-up" @disabled($scopePositionLoop->first) class="btn btn-sm btn-icon btn-ghost-secondary" aria-label="Move scope up">
                <x-tabler.icon name="arrow-up" />
            </button>
        </form>
        <form method="POST" action="{{ route('operations.repair-orders.concerns.move', [$repairOrder, $concern]) }}" data-refresh-scope="worksheet" data-continuity-focus="#concern-{{ $concern->id }} button[name='move-down']" @submit.prevent="submitWorksheetForm($event)">
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <input type="hidden" name="direction" value="down">
            <button type="submit" name="move-down" @disabled($scopePositionLoop->last) class="btn btn-sm btn-icon btn-ghost-secondary" aria-label="Move scope down">
                <x-tabler.icon name="arrow-down" />
            </button>
        </form>
        @if (($canMoveScopeToNewRo ?? false) && ! $isTerminal)
            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.concerns.move-to-new-ro', [$repairOrder, $concern]) }}"
                    onsubmit="return confirm('Move this entire scope and all its lines to a new draft repair order for the same vehicle?')"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <button
                        type="submit"
                        class="btn btn-sm btn-ghost-secondary"
                        title="Move this complete scope onto a new draft repair order"
                    >
                        Move to new RO
                    </button>
                </form>
            @endcan
        @endif
        @if ($concern->lines->isEmpty())
            @can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersDestructive->value)
                <form method="POST" action="{{ route('operations.repair-orders.concerns.destroy', [$repairOrder, $concern]) }}" data-refresh-scope="worksheet" data-continuity-focus="#concern-store [name='summary']" @submit.prevent="submitWorksheetForm($event)">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <button type="submit" class="btn btn-sm btn-ghost-danger" aria-label="Delete empty concern" title="Remove this concern after all lines are deleted">
                        Delete concern
                    </button>
                </form>
            @endcan
        @endif
    </div>
</div>
