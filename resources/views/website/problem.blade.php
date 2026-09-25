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

        <p class="mt-6">
            <a class="public-cta public-cta--primary" href="{{ route('public.book') }}">Book an appointment</a>
        </p>
    </article>
</x-website.layout>
