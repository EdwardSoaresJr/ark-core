@php
    $authorViaModal = (bool) ($authorViaModal ?? false);
    $scopePositionLoop = $loop;
@endphp
<details
    class="ops-scope-more"
    x-data
    @click.outside="$el.open = false"
    @keydown.escape.prevent="$el.open = false"
>
    <summary class="ops-scope-more__trigger" aria-label="More actions" aria-haspopup="menu">More</summary>
    <div class="ops-scope-more__panel" role="menu">
        @if ($concern->tracksProduction() && ! ($productionInHeader ?? false))
            <div class="ops-scope-more__field">
                <p class="ops-scope-more__label">Production</p>
                @include('operations.repair-orders.partials.repair-order-concern-production-status-control', [
                    'repairOrder' => $repairOrder,
                    'concern' => $concern,
                    'isTerminal' => $isTerminal,
                    'estimateVersion' => $estimateVersion,
                    'authorViaModal' => $authorViaModal,
                ])
            </div>
        @endif
        @if ($concern->shouldSurfaceRecommendationStatus())
            <div class="ops-scope-more__field">
                <p class="ops-scope-more__label">Recommendation</p>
                @include('operations.repair-orders.partials.repair-order-concern-recommendation-intent-control', [
                    'repairOrder' => $repairOrder,
                    'concern' => $concern,
                    'isTerminal' => $isTerminal,
                    'estimateVersion' => $estimateVersion,
                    'authorViaModal' => $authorViaModal,
                ])
            </div>
        @endif
        <div class="ops-scope-more__field">
            <p class="ops-scope-more__label">Billing</p>
            @include('operations.repair-orders.partials.repair-order-concern-billing-control', [
                'repairOrder' => $repairOrder,
                'concern' => $concern,
                'isTerminal' => $isTerminal,
                'estimateVersion' => $estimateVersion,
                'laborRate' => $concernDefaultLaborRate ?? $laborRate ?? null,
                'authorViaModal' => $authorViaModal,
            ])
        </div>
        <form method="POST" action="{{ route('operations.repair-orders.concerns.move', [$repairOrder, $concern]) }}" data-refresh-scope="worksheet" data-continuity-focus="#concern-{{ $concern->id }} button[name='move-up']" @submit.prevent="submitWorksheetForm($event)">
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <input type="hidden" name="direction" value="up">
            <button type="submit" name="move-up" @disabled($scopePositionLoop->first) class="ops-scope-more__item" role="menuitem" aria-label="Move scope up">
                Move up
            </button>
        </form>
        <form method="POST" action="{{ route('operations.repair-orders.concerns.move', [$repairOrder, $concern]) }}" data-refresh-scope="worksheet" data-continuity-focus="#concern-{{ $concern->id }} button[name='move-down']" @submit.prevent="submitWorksheetForm($event)">
            @csrf
            @method('PATCH')
            <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
            <input type="hidden" name="direction" value="down">
            <button type="submit" name="move-down" @disabled($scopePositionLoop->last) class="ops-scope-more__item" role="menuitem" aria-label="Move scope down">
                Move down
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
                        class="ops-scope-more__item"
                        role="menuitem"
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
                    <button type="submit" class="ops-scope-more__item" role="menuitem" aria-label="Delete empty concern" title="Remove this concern after all lines are deleted">
                        Delete concern
                    </button>
                </form>
            @endcan
        @endif
        @include('operations.repair-orders.partials.repair-order-concern-authorization-exception', [
            'exceptionSurface' => 'menu',
            'repairOrder' => $repairOrder,
            'concern' => $concern,
            'isTerminal' => $isTerminal,
            'estimateVersion' => $estimateVersion,
        ])
    </div>
</details>
