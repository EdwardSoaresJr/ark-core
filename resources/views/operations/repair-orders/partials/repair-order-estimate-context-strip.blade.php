@php
    $awareness = $recommendationAwareness ?? ['open_count' => 0, 'safety_count' => 0, 'headline' => null, 'strong' => false];
    $partsBlockingCount = (int) ($partsBlockingCount ?? 0);
    $showParts = $partsBlockingCount > 0
        || $repairOrder->lines->contains(fn ($line) => $line->type->isPart());
@endphp

{{--
  Authorization / Customer View / Parts nav removed.
  Entry points: Approval KPI → auth, Customer total KPI → portal, Parts KPI → parts,
  footer Customer Display + portal Preview links remain.
--}}
<div class="mb-0" id="estimate-context-rail" data-estimate-context>
    @if ($awareness['headline'])
        <div class="mb-2">
            <button
                type="button"
                @class([
                    'btn btn-sm',
                    'btn-ghost-orange' => ! $awareness['strong'],
                    'btn-ghost-danger' => $awareness['strong'],
                ])
                @click="window.arkSelectRepairOrderWorkspaceTab && window.arkSelectRepairOrderWorkspaceTab('recommendations')"
            >
                {{ $awareness['headline'] }}
            </button>
        </div>
    @endif

    <div
        id="authorization-rail"
        data-continuity-region="authorization-rail"
        class="card card-sm mt-2"
        x-show="estimateContext === 'auth'"
        x-cloak
    >
        <div class="card-body">
            @include('operations.repair-orders.partials.repair-order-rail-tab-auth', [
                'repairOrder' => $repairOrder,
                'totals' => $totals,
                'isTerminal' => $isTerminal ?? false,
                'estimateVersion' => $estimateVersion,
            ])
        </div>
    </div>

    <div
        id="customer-view"
        class="card card-sm mt-2"
        x-show="estimateContext === 'portal'"
        x-cloak
    >
        <div class="card-body">
            @include('operations.repair-orders.partials.repair-order-rail-tab-portal', [
                'repairOrder' => $repairOrder,
                'isTerminal' => $isTerminal ?? false,
            ])
        </div>
    </div>

    @if ($showParts)
        <div
            id="parts-procurement"
            class="card card-sm mt-2"
            x-show="estimateContext === 'parts'"
            x-cloak
        >
            <div class="card-body">
                @include('operations.repair-orders.partials.repair-order-rail-tab-parts', [
                    'repairOrder' => $repairOrder,
                    'partsBlockingCount' => $partsBlockingCount,
                    'partsReadinessCounts' => $partsReadinessCounts ?? [],
                    'isTerminal' => $isTerminal ?? false,
                ])
            </div>
        </div>
    @endif
</div>
