<x-website.layout :website="$website" :seo="$seo" page="common-problems">
    <article class="public-cp-index">
        <h1 class="public-page-title">Common problems</h1>
        <p class="public-page-lede">{{ $seo['description'] }}</p>
        <ul class="public-home-problems__list">
            @foreach ($website->problems() as $problem)
                <li>
                    <a class="public-home-problems__link" href="{{ route('public.common-problems.show', $problem['slug']) }}">
                        {{ $problem['title'] ?? $problem['slug'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </article>
</x-website.layout>
