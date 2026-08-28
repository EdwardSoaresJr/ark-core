@php
    /** @var \App\Ark\Operations\ArkManager\ArkManagerMorningBrief $arkManagerBrief */
@endphp

<section class="ops-today__section ops-today-ai" aria-labelledby="ops-today-ai">
    <div class="ops-today__section-header">
        <h2 id="ops-today-ai" class="ops-today__section-title">
            ARK Manager
            @if ($arkManagerBrief->aiEnhanced)
                <span class="ops-today-ai__badge">AI brief</span>
            @else
                <span class="ops-today-ai__badge ops-today-ai__badge--deterministic">Read-only brief</span>
            @endif
        </h2>
        <p class="ops-today__section-copy">
            Explains pipeline, flow, and recommendations — never ranks or mutates operational truth.
            Priority order always comes from deterministic rules above.
        </p>
    </div>

    <article class="ops-today-ai__brief" aria-label="Morning brief">
        @foreach ($arkManagerBrief->paragraphs as $paragraph)
            <p class="ops-today-ai__paragraph">{!! nl2br(e($paragraph)) !!}</p>
        @endforeach

        <p class="ops-today-ai__focus">
            <span class="ops-today-ai__focus-label">Focus</span>
            <span class="ops-today-ai__focus-value">{{ $arkManagerBrief->recommendedFocus }}</span>
        </p>
    </article>

    <p class="ops-today-ai__footnote">
        Draft communications require human approval before sending. ARK Manager does not send messages or change repair orders.
    </p>
</section>
