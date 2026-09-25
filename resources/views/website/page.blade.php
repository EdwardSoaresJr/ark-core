<x-website.layout :website="$website" :seo="$seo" :page="$pageKey">
    <article class="public-static-page">
        <h1 class="public-page-title">{{ $page['title'] }}</h1>
        @if ($page['lede'] !== '')
            <p class="public-page-lede">{{ $page['lede'] }}</p>
        @endif
        @foreach ($page['sections'] as $section)
            <section class="public-content-section mt-6">
                @if ($section['heading'] !== '')
                    <h2>{{ $section['heading'] }}</h2>
                @endif
                @if ($section['body'] !== '')
                    <p>{{ $section['body'] }}</p>
                @endif
            </section>
        @endforeach
    </article>
</x-website.layout>
