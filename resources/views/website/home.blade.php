<x-website.layout :website="$website" :seo="$seo" page="home">
    <article class="public-static-page">
        <p class="public-page-eyebrow">{{ $website->shopName() }}</p>
        <h1 class="public-page-title mt-2">{{ $website->headline() }}</h1>
        @if ($website->lede() !== '')
            <p class="public-page-lede">{{ $website->lede() }}</p>
        @endif
        @if ($website->localTagline() !== '')
            <p class="public-page-lede">{{ $website->localTagline() }}</p>
        @endif

        <p class="mt-6 flex flex-wrap gap-3">
            <a class="public-cta public-cta--primary" href="{{ route('public.book') }}">Book an appointment</a>
            @if ($website->phone() !== '')
                <a class="public-cta public-cta--secondary" href="tel:{{ preg_replace('/\D+/', '', $website->phone()) }}">Call {{ $website->phoneDisplay() }}</a>
            @endif
        </p>

        @if ($website->googleRating() !== '')
            <p class="mt-4 text-sm text-slate-600">
                {{ $website->googleRating() }}
                @if ($website->googleReviewCount() > 0)
                    from {{ $website->googleReviewCount() }} Google reviews
                @endif
                @if ($website->googleReviewsUrl() !== '')
                    - <a class="public-link" href="{{ $website->googleReviewsUrl() }}">Google reviews</a>
                @endif
            </p>
        @endif
    </article>

    @if ($website->featuredProblems() !== [])
        <section class="public-home-problems mt-10">
            <h2 class="public-home-problems__title">Common problems</h2>
            <p class="public-home-problems__lede">What the symptom can mean, and whether you can keep driving.</p>
            <ul class="public-home-problems__list">
                @foreach ($website->featuredProblems() as $problem)
                    <li>
                        <a class="public-home-problems__link" href="{{ route('public.common-problems.show', $problem['slug']) }}">
                            {{ $problem['title'] ?? $problem['slug'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="public-home-problems__actions">
                <a class="public-home-problems__view-all" href="{{ route('public.common-problems.index') }}">All common problems</a>
            </p>
        </section>
    @endif

    @if ($website->reviews() !== [])
        <section class="mt-10 max-w-3xl">
            <h2 class="public-page-title">What drivers say</h2>
            @foreach ($website->reviews() as $review)
                <blockquote class="mt-4">
                    <p>{{ $review['quote'] }}</p>
                    @if ($review['attribution'] !== '')
                        <footer class="mt-1 text-sm text-slate-600">{{ $review['attribution'] }}</footer>
                    @endif
                </blockquote>
            @endforeach
        </section>
    @endif
</x-website.layout>
