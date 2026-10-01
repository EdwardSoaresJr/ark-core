@php
    /** @var \App\Ark\Operations\Today\AdvisorHomeCockpitProjection $cockpit */
    $nextRow = $cockpit->nextAttentionRow;
    $nextGaugeLabel = $cockpit->needsCallCount > 0 ? 'Next Call' : 'Next';
@endphp

<header class="ops-advisor-home-cockpit ops-board-header" aria-label="Shop context">
    <div class="ops-advisor-home-cockpit__row">
        <div class="ops-advisor-home-cockpit__brand">
            <h1 class="ops-board-title">Active Cars</h1>
            <span class="ops-board-badge">{{ $cockpit->activeCarCount }}</span>
        </div>

        <div class="ops-advisor-home-cockpit__signals">
            <div class="ops-advisor-home-cockpit__signal">
                <span class="ops-advisor-home-cockpit__label">Needs Action</span>
                <span class="ops-advisor-home-cockpit__value">
                    <strong>{{ $cockpit->needsActionCount }}</strong>
                </span>
            </div>

            @if ($cockpit->pipelineRoCount > 0)
                <div class="ops-advisor-home-cockpit__signal">
                    <span class="ops-advisor-home-cockpit__label">Pipeline</span>
                    @if ($cockpit->pipelineInventoryUrl)
                        <a href="{{ $cockpit->pipelineInventoryUrl }}" class="ops-advisor-home-cockpit__value ops-advisor-home-cockpit__value--link">
                            <strong>{{ $cockpit->pipelineAmountLabel }}</strong>
                            <span class="ops-advisor-home-cockpit__sep">·</span>
                            {{ $cockpit->pipelineRoCount }}
                        </a>
                    @else
                        <span class="ops-advisor-home-cockpit__value">
                            <strong>{{ $cockpit->pipelineAmountLabel }}</strong>
                        </span>
                    @endif
                </div>
            @endif

            @if ($cockpit->largestPendingApproval !== null)
                <div class="ops-advisor-home-cockpit__signal ops-advisor-home-cockpit__signal--gauge">
                    <span class="ops-advisor-home-cockpit__label">Biggest Pending</span>
                    <a href="{{ $cockpit->largestPendingApproval->href }}" class="ops-advisor-home-cockpit__gauge ops-advisor-home-cockpit__gauge--link">
                        <strong class="ops-advisor-home-cockpit__gauge-name">{{ $cockpit->largestPendingApproval->label }}</strong>
                        @if ($cockpit->largestPendingApproval->metaLabel)
                            <span class="ops-advisor-home-cockpit__gauge-meta">{{ $cockpit->largestPendingApproval->metaLabel }} pending</span>
                        @endif
                    </a>
                </div>
            @endif

            @if ($nextRow !== null && $cockpit->nextRecommendation !== null)
                <div class="ops-advisor-home-cockpit__signal ops-advisor-home-cockpit__signal--gauge">
                    <span class="ops-advisor-home-cockpit__label">{{ $nextGaugeLabel }}</span>
                    <a href="{{ $cockpit->nextRecommendation->repairOrderUrl }}" class="ops-advisor-home-cockpit__gauge ops-advisor-home-cockpit__gauge--link">
                        <strong class="ops-advisor-home-cockpit__gauge-name">{{ $nextRow->customerName }}</strong>
                        @if ($nextRow->totalLabel)
                            <span class="ops-advisor-home-cockpit__gauge-meta">{{ $nextRow->totalLabel }} pending</span>
                        @endif
                    </a>
                </div>
            @endif
        </div>
    </div>
</header>
