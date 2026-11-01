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

<div class="mb-3" id="estimate-context-rail" data-estimate-context>
    <div class="nav nav-pills gap-1" role="toolbar" aria-label="Estimate context">
        <button
            type="button"
            id="authorization-rail"
            data-continuity-region="authorization-rail"
            class="nav-link"
            :class="estimateContext === 'auth' ? 'active' : ''"
            :aria-expanded="estimateContext === 'auth'"
            @click="toggleEstimateContext('auth')"
        >
            @if ($lastApproval)
                <span class="text-green me-1" aria-hidden="true">✓</span>
                <span>Authorization {{ $authorizedAmount }}</span>
            @else
                Authorization
            @endif
        </button>

        <button
            type="button"
            id="customer-view"
            class="nav-link"
            :class="estimateContext === 'portal' ? 'active' : ''"
            :aria-expanded="estimateContext === 'portal'"
            @click="toggleEstimateContext('portal')"
        >
            Customer View
        </button>

        @if ($showParts)
            <button
                type="button"
                id="parts-procurement"
                class="nav-link"
                :class="estimateContext === 'parts' ? 'active' : ''"
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
                    'nav-link',
                    'text-orange' => ! $awareness['strong'],
                    'text-red fw-bold' => $awareness['strong'],
                ])
                @click="window.arkSelectRepairOrderWorkspaceTab && window.arkSelectRepairOrderWorkspaceTab('recommendations')"
            >
                {{ $awareness['headline'] }}
            </button>
        @endif
    </div>

    <div class="card card-sm mt-2" x-show="estimateContext === 'auth'" x-cloak>
        <div class="card-body">
            @include('operations.repair-orders.partials.repair-order-rail-tab-auth', [
                'repairOrder' => $repairOrder,
                'totals' => $totals,
                'isTerminal' => $isTerminal ?? false,
                'estimateVersion' => $estimateVersion,
            ])
        </div>
    </div>

    <div class="card card-sm mt-2" x-show="estimateContext === 'portal'" x-cloak>
        <div class="card-body">
            @include('operations.repair-orders.partials.repair-order-rail-tab-portal', [
                'repairOrder' => $repairOrder,
                'isTerminal' => $isTerminal ?? false,
            ])
        </div>
    </div>

    @if ($showParts)
        <div class="card card-sm mt-2" x-show="estimateContext === 'parts'" x-cloak>
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
