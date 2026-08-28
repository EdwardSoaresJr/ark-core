@php
    /** @var array $authority */
@endphp

{{-- Answer ("What's going on") is rendered answer-first in show.blade.php --}}

<section class="public-content-section">
    <h2>Symptoms</h2>
    <ul class="public-bullet-list">
        @foreach ($authority['symptoms'] as $symptom)
            <li>{{ $symptom }}</li>
        @endforeach
    </ul>
</section>

@if ($authority['can_drive_is_safety'] ?? false)
    <section class="public-cp-callout public-cp-callout--drive">
        <h2>{{ $authority['can_drive_heading'] }}</h2>
        <div class="public-cp-callout__body">
            @foreach ($authority['can_drive'] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    </section>
@else
    <section class="public-content-section">
        <h2>{{ $authority['can_drive_heading'] }}</h2>
        <ul class="public-bullet-list">
            @foreach ($authority['can_drive'] as $paragraph)
                <li>{{ $paragraph }}</li>
            @endforeach
        </ul>
    </section>
@endif

<section class="public-content-section">
    <h2>Common causes</h2>
    <ul class="public-bullet-list">
        @foreach ($authority['common_causes'] as $cause)
            <li>{{ $cause }}</li>
        @endforeach
    </ul>
</section>

@if ($authority['often_confused_with'] !== [])
    <section class="public-content-section">
        <h2>{{ $authority['often_confused_heading'] }}</h2>
        <ul class="public-bullet-list">
            @foreach ($authority['often_confused_with'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>
@endif

@if ($authority['if_you_ignore'] !== [])
    <section class="public-cp-callout public-cp-callout--muted">
        <h2>If you wait</h2>
        <ul class="public-bullet-list">
            @foreach ($authority['if_you_ignore'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>
@endif

@if ($authority['diagnostic_process'] !== [])
    <section class="public-content-section">
        <h2>How we check it</h2>
        <ol class="public-numbered-list">
            @foreach ($authority['diagnostic_process'] as $index => $step)
                <li>
                    <span class="public-step-marker">{{ $index + 1 }}</span>
                    <span>{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    </section>
@endif

@if ($authority['typical_repairs'] !== [] && $authority['typical_repairs'] !== $authority['diagnostic_process'])
    <section class="public-content-section">
        <h2>What repair usually involves</h2>
        <ul class="public-bullet-list">
            @foreach ($authority['typical_repairs'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </section>
@endif

@include('partials.public.common-problem-shop-experience', [
    'shopExperience' => $authority['shop_experience'],
])

<section class="public-content-section">
    <h2>What happens next when you bring it in?</h2>
    <ol class="public-numbered-list">
        @foreach ($authority['what_happens_next'] as $index => $step)
            <li>
                <span class="public-step-marker">{{ $index + 1 }}</span>
                <span>{{ $step }}</span>
            </li>
        @endforeach
    </ol>
</section>

@if ($authority['faq'] !== [])
    <section class="public-content-section">
        <h2>Questions we get a lot</h2>
        <dl class="public-faq-list">
            @foreach ($authority['faq'] as $item)
                <div>
                    <dt>{{ $item['question'] }}</dt>
                    <dd>{{ $item['answer'] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
@endif

@if ($authority['related_problems'] !== [])
    <section class="public-content-section public-cp-related">
        <h2>Related problems</h2>
        <ul class="public-cp-related__list">
            @foreach ($authority['related_problems'] as $related)
                <li>
                    <a href="{{ $related['href'] }}" class="public-link">{{ $related['title'] }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
