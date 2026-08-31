@php
    $authorViaModal = (bool) ($authorViaModal ?? false);
@endphp
<div class="ops-scope-settings">
    <div class="ops-scope-settings__controls">
        @if ($concern->shouldSurfaceRecommendationStatus())
            @include('operations.repair-orders.partials.repair-order-concern-recommendation-intent-control', [
                'repairOrder' => $repairOrder,
                'concern' => $concern,
                'isTerminal' => $isTerminal,
                'estimateVersion' => $estimateVersion,
                'authorViaModal' => $authorViaModal,
            ])
        @endif
        @include('operations.repair-orders.partials.repair-order-concern-billing-control', [
            'repairOrder' => $repairOrder,
            'concern' => $concern,
            'isTerminal' => $isTerminal,
            'estimateVersion' => $estimateVersion,
            'laborRate' => $concernDefaultLaborRate ?? $laborRate ?? null,
            'authorViaModal' => $authorViaModal,
        ])
        <div class="ops-scope-header-toolbar-actions">
            <form method="POST" action="{{ route('operations.repair-orders.concerns.move', [$repairOrder, $concern]) }}" data-refresh-scope="worksheet" data-continuity-focus="#concern-{{ $concern->id }} button[name='move-up']" @submit.prevent="submitWorksheetForm($event)">
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="direction" value="up">
                <button type="submit" name="move-up" @disabled($loop->first) class="ops-scope-settings__move" aria-label="Move scope up">
                    ↑
                </button>
            </form>
            <form method="POST" action="{{ route('operations.repair-orders.concerns.move', [$repairOrder, $concern]) }}" data-refresh-scope="worksheet" data-continuity-focus="#concern-{{ $concern->id }} button[name='move-down']" @submit.prevent="submitWorksheetForm($event)">
                @csrf
                @method('PATCH')
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <input type="hidden" name="direction" value="down">
                <button type="submit" name="move-down" @disabled($loop->last) class="ops-scope-settings__move" aria-label="Move scope down">
                    ↓
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
                            class="ops-scope-settings__move-to-ro"
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
                        <button type="submit" class="ops-scope-settings__delete" aria-label="Delete empty concern" title="Remove this concern after all lines are deleted">
                            Delete concern
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>
</div>
