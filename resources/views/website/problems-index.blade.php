<x-website.layout :website="$website" :seo="$seo" page="common-problems">
    <article class="public-cp-index">
        <h1 class="public-page-title">Problems and codes</h1>
        <p class="public-page-lede">What a symptom or a code can mean, whether you can keep driving, and how we find the cause.</p>
        <p class="public-cp-index__lede-secondary">The work itself is on <a class="public-link" href="{{ route('public.services') }}">Services</a>.</p>

        <section class="public-cp-index__catalog">
            <h2>Problems and symptoms</h2>
            <ul class="public-cp-index__list">
                @foreach ($groups['symptoms'] as $problem)
                    <li>
                        <a class="public-cp-index__link" href="{{ route('public.common-problems.show', $problem['slug']) }}">
                            <span class="public-cp-index__link-title">{{ $problem['title'] ?? $problem['slug'] }}</span>
                            @if (! empty($problem['card_teaser']))
                                <span class="public-cp-index__link-teaser">{{ $problem['card_teaser'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="public-cp-index__catalog">
            <h2>Diagnostic codes</h2>
            <ul class="public-cp-index__list">
                @foreach ($groups['codes'] as $problem)
                    <li>
                        <a class="public-cp-index__link" href="{{ route('public.common-problems.show', $problem['slug']) }}">
                            <span class="public-cp-index__link-title">{{ $problem['title'] ?? $problem['slug'] }}</span>
                            @if (! empty($problem['card_teaser']))
                                <span class="public-cp-index__link-teaser">{{ $problem['card_teaser'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </article>
</x-website.layout>
