@php
    $awareness = $recommendationAwareness ?? ['open_count' => 0, 'safety_count' => 0, 'headline' => null, 'strong' => false];
    $lastApproval = $repairOrder->relationLoaded('approvalEvents')
        ? $repairOrder->approvalEvents->first(fn ($event) => ! $event->isRevoked())
        : $repairOrder->approvalEvents()->with('revocation')->get()->first(fn ($event) => ! $event->isRevoked());
    $approvedTotals = app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)->approvedTotalsForRead($repairOrder);
    $partLineCount = $repairOrder->lines->contains(fn ($line) => $line->type->isPart())
        ? $repairOrder->lines->filter(fn ($line) => $line->type->isPart())->count()
        : 0;
    $partsBlockingCount = (int) ($partsBlockingCount ?? 0);
    $showParts = $partsBlockingCount > 0 || $partLineCount > 0;
    $authorizedAmount = $lastApproval
        ? $approvedTotals->format($approvedTotals->totalCents())
        : null;
@endphp

<div class="ops-estimate-context-rail" id="estimate-context-rail">
    <div class="ops-estimate-context-rail__items" role="toolbar" aria-label="Estimate context">
        <button
            type="button"
            class="ops-estimate-context-rail__item"
            :class="!estimateContext ? 'is-active' : ''"
            :aria-pressed="(!estimateContext).toString()"
            @click="estimateContext = null; window.arkSelectRepairOrderWorkspaceTab && window.arkSelectRepairOrderWorkspaceTab('builder')"
        >Home</button>

        <button
            type="button"
            id="authorization-rail"
            class="ops-estimate-context-rail__item"
            :class="estimateContext === 'auth' ? 'is-active' : ''"
            :aria-expanded="estimateContext === 'auth'"
            @click="toggleEstimateContext('auth')"
        >
            @if ($lastApproval)
                <span class="ops-estimate-context-rail__mark" aria-hidden="true">✓</span>
                <span>Authorization {{ $authorizedAmount }}</span>
            @else
                Authorization
            @endif
        </button>

        <button
            type="button"
            id="customer-view"
            class="ops-estimate-context-rail__item"
            :class="estimateContext === 'portal' ? 'is-active' : ''"
            :aria-expanded="estimateContext === 'portal'"
            @click="toggleEstimateContext('portal')"
        >
            Customer View
        </button>

        @if ($showParts)
            <button
                type="button"
                id="parts-procurement"
                class="ops-estimate-context-rail__item"
                :class="estimateContext === 'parts' ? 'is-active' : ''"
                :aria-expanded="estimateContext === 'parts'"
                @click="toggleEstimateContext('parts')"
            >
                @if ($partsBlockingCount > 0)
                    Parts {{ $partLineCount }} · {{ $partsBlockingCount }} blocking
                @else
                    Parts {{ $partLineCount }} · Ready
                @endif
            </button>
        @endif

        @if ($awareness['headline'])
            <button
                type="button"
                @class([
                    'ops-estimate-context-rail__item ops-estimate-context-rail__item--recs',
                    'is-strong' => $awareness['strong'],
                ])
                @click="window.arkSelectRepairOrderWorkspaceTab && window.arkSelectRepairOrderWorkspaceTab('recommendations')"
            >
                {{ $awareness['headline'] }}
            </button>
        @endif
    </div>

    <div class="ops-estimate-context-rail__panel" x-show="estimateContext === 'auth'" x-cloak>
        @include('operations.repair-orders.partials.repair-order-rail-tab-auth', [
            'repairOrder' => $repairOrder,
            'totals' => $totals,
            'isTerminal' => $isTerminal ?? false,
            'estimateVersion' => $estimateVersion,
        ])
    </div>

    <div class="ops-estimate-context-rail__panel" x-show="estimateContext === 'portal'" x-cloak>
        @include('operations.repair-orders.partials.repair-order-rail-tab-portal', [
            'repairOrder' => $repairOrder,
            'isTerminal' => $isTerminal ?? false,
        ])
    </div>

    @if ($showParts)
        <div class="ops-estimate-context-rail__panel" x-show="estimateContext === 'parts'" x-cloak>
            @include('operations.repair-orders.partials.repair-order-rail-tab-parts', [
                'repairOrder' => $repairOrder,
                'partsBlockingCount' => $partsBlockingCount,
                'partsReadinessCounts' => $partsReadinessCounts ?? [],
                'isTerminal' => $isTerminal ?? false,
            ])
        </div>
    @endif
</div>
