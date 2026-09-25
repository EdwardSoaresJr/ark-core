<x-website.layout :website="$website" :seo="$seo" :page="$pageKey">
    @if ($pageKey === 'warranty')
        <div class="public-canvas">
            <div class="public-canvas__layout public-canvas__layout--read">
                <article>
                    <h1 class="public-page-title">{{ $page['title'] }}</h1>
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
                @if ($page['lede'] !== '')
                    <aside class="public-canvas__rail">
                        @foreach (preg_split('/(?<=\.)\s+(?=[A-Z])/', $page['lede']) ?: [] as $note)
                            @if (trim($note) !== '')
                                <p class="public-page-lede">{{ trim($note) }}</p>
                            @endif
                        @endforeach
                    </aside>
                @endif
            </div>
        </div>
    @else
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
    @endif
</x-website.layout>
