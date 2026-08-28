@php
    $partstechCatalogUrl = $partstechCatalogUrl ?? null;
    $partstechBlockedReason = $partstechBlockedReason ?? null;
    $partstechPoNumber = $partstechPoNumber ?? null;
    $showCatalog = $showCatalog ?? false;
    $showPullQuote = $showPullQuote ?? false;
@endphp

@if ($showCatalog && $showPullQuote)
    <div class="ops-review-action-group ops-review-action-group--procurement" role="group" aria-label="PartsTech">
        <button
            type="button"
            class="ops-review-action ops-review-action--procurement ops-review-action-group__segment"
            @if ($partstechCatalogUrl)
                @click="openPartsTechCatalog()"
                :disabled="partstechCatalogOpening || partstechPullLoading"
                title="Open PartsTech catalog for RO {{ $partstechPoNumber }}"
            @else
                disabled
                title="{{ $partstechBlockedReason }}"
            @endif
        >
            <span x-show="! partstechCatalogOpening" x-cloak>PartsTech</span>
            <span x-show="partstechCatalogOpening" x-cloak class="inline-flex items-center gap-1.5">
                <span class="ops-partstech-loader ops-partstech-loader--inline" aria-hidden="true"></span>
                Opening…
            </span>
        </button>
        <button
            type="button"
            class="ops-review-action ops-review-action--procurement ops-review-action-group__segment ops-review-action-group__segment--secondary"
            :disabled="partstechPullLoading || partstechCatalogOpening"
            @click="$dispatch('ark:partstech-pull-quote', partstechPreferredConcernId ? { concernId: partstechPreferredConcernId } : {})"
            title="Pull active PartsTech quote into this estimate"
        >
            <span x-show="! partstechPullLoading" x-cloak>Pull Quote</span>
            <span x-show="partstechPullLoading" x-cloak class="inline-flex items-center gap-1.5">
                <span class="ops-partstech-loader ops-partstech-loader--inline" aria-hidden="true"></span>
                <span x-text="partstechPullStatus || 'Pulling…'"></span>
            </span>
        </button>
    </div>
@elseif ($showCatalog)
    <button
        type="button"
        class="ops-review-action ops-review-action--procurement"
        @if ($partstechCatalogUrl)
            @click="openPartsTechCatalog()"
            :disabled="partstechCatalogOpening || partstechPullLoading"
            title="Open PartsTech catalog for RO {{ $partstechPoNumber }}"
        @else
            disabled
            title="{{ $partstechBlockedReason }}"
        @endif
    >
        <span x-show="! partstechCatalogOpening" x-cloak>PartsTech</span>
        <span x-show="partstechCatalogOpening" x-cloak class="inline-flex items-center gap-1.5">
            <span class="ops-partstech-loader ops-partstech-loader--inline" aria-hidden="true"></span>
            Opening…
        </span>
    </button>
@elseif ($showPullQuote)
    <button
        type="button"
        class="ops-review-action ops-review-action--procurement"
        :disabled="partstechPullLoading || partstechCatalogOpening"
        @click="$dispatch('ark:partstech-pull-quote', partstechPreferredConcernId ? { concernId: partstechPreferredConcernId } : {})"
        title="Pull active PartsTech quote into this estimate"
    >
        <span x-show="! partstechPullLoading" x-cloak>Pull Quote</span>
        <span x-show="partstechPullLoading" x-cloak class="inline-flex items-center gap-1.5">
            <span class="ops-partstech-loader ops-partstech-loader--inline" aria-hidden="true"></span>
            <span x-text="partstechPullStatus || 'Pulling…'"></span>
        </span>
    </button>
@endif
