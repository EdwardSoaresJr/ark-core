<x-website.layout :website="$website" :seo="$seo" :page="$pageKey">
    <article class="public-static-page">
        <h1 class="public-page-title">{{ $page['title'] }}</h1>
        @if ($page['lede'] !== '')
            <p class="public-page-lede">{{ $page['lede'] }}</p>
        @endif
        @if (($page['links'] ?? []) !== [])
            <ul class="mt-4">
                @foreach ($page['links'] as $link)
                    <li><a class="public-link" href="{{ $link['path'] }}">{{ $link['label'] }}</a></li>
                @endforeach
            </ul>
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
