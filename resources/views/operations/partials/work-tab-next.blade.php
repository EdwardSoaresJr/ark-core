@php
    /** @var \App\Ark\Operations\Today\AdvisorTodayProjection $morningBrief */
    /** @var \App\Ark\Operations\ArkManager\ArkManagerMorningBrief $arkManagerBrief */
    $briefRecommendations = $morningBrief->briefRecommendations();
    $moreRecommendations = $morningBrief->additionalRecommendationCount();
@endphp

<div class="ops-work-tab-panel ops-today">
    @if ($errors->has('close_lost'))
        <p class="ops-today__error" role="alert">{{ $errors->first('close_lost') }}</p>
    @endif

    <section class="ops-today__section" aria-labelledby="ops-morning-recommendations">
        <div class="ops-today__section-header">
            <h2 id="ops-morning-recommendations" class="ops-today__section-title">Recommended next</h2>
            <p class="ops-today__section-copy">Ranked by deterministic rules — not AI. Every row shows why it surfaced.</p>
        </div>

        @if ($briefRecommendations === [])
            <p class="ops-today__empty">No ranked recommendations right now. Check My work or open the workboard.</p>
        @else
            <div class="ops-today__recommendations">
                @foreach ($briefRecommendations as $recommendation)
                    @include('operations.today.partials.recommendation-card', ['recommendation' => $recommendation])
                @endforeach
            </div>

            @if ($moreRecommendations > 0)
                <p class="ops-morning-brief__more">
                    <a href="{{ route('operations.workboard') }}" class="ops-page-link">
                        {{ $moreRecommendations }} more on workboard →
                    </a>
                </p>
            @endif
        @endif
    </section>

    <details class="ops-morning-brief__manager">
        <summary class="ops-morning-brief__manager-summary">ARK Manager brief</summary>
        @include('operations.today.partials.ai-panel', ['arkManagerBrief' => $arkManagerBrief])
    </details>
</div>
