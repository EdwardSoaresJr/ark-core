<x-website.layout :website="$website" :seo="$seo" :page="'common-problems.'.($problem['slug'] ?? '')">
    <article class="public-cp-article">
        <h1 class="public-cp-title public-page-title">{{ $problem['title'] ?? '' }}</h1>
        @if (! empty($problem['problem']))
            <p class="public-cp-meaning">{{ $problem['problem'] }}</p>
        @endif

        @if (! empty($problem['symptoms']))
            <section class="public-content-section mt-6">
                <h2>Symptoms</h2>
                <ul>
                    @foreach ($problem['symptoms'] as $symptom)
                        <li>{{ $symptom }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($problem['can_drive']))
            <section class="public-content-section mt-6">
                <h2>{{ $problem['can_drive_heading'] ?? 'Can I keep driving?' }}</h2>
                <ul>
                    @foreach ($problem['can_drive'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($problem['common_causes']))
            <section class="public-content-section mt-6">
                <h2>Common causes</h2>
                <ul>
                    @foreach ($problem['common_causes'] as $cause)
                        <li>{{ $cause }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($problem['often_confused_with']))
            <section class="public-content-section mt-6">
                <h2>Often confused with</h2>
                <ul>
                    @foreach ($problem['often_confused_with'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($problem['if_you_ignore']))
            <section class="public-content-section mt-6">
                <h2>If you ignore it</h2>
                <ul>
                    @foreach ($problem['if_you_ignore'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($problem['repair_overview']))
            <section class="public-content-section mt-6">
                <h2>Repair overview</h2>
                <ul>
                    @foreach ($problem['repair_overview'] as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($problem['what_happens_next']))
            <section class="public-content-section mt-6">
                <h2>What happens next</h2>
                <ul>
                    @foreach ($problem['what_happens_next'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (! empty($problem['faq']))
            <section class="public-content-section mt-6">
                <h2>Questions</h2>
                @foreach ($problem['faq'] as $item)
                    @if (! empty($item['question']))
                        <h3 class="mt-4 font-semibold">{{ $item['question'] }}</h3>
                        <p>{{ $item['answer'] ?? '' }}</p>
                    @endif
                @endforeach
            </section>
        @endif

        @if (! empty($problem['related_problem_slugs']))
            <section class="public-content-section mt-6">
                <h2>Related</h2>
                <ul>
                    @foreach ($problem['related_problem_slugs'] as $related)
                        <li><a class="public-link" href="{{ route('public.common-problems.show', $related) }}">{{ $related }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="mt-6">
            <a class="public-cta public-cta--primary" href="{{ route('public.book') }}">Book an appointment</a>
        </p>
    </article>
</x-website.layout>
