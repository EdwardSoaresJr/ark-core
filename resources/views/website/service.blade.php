<x-website.layout :website="$website" :seo="$seo" :page="'services.'.$page['id']">
    <div class="public-service">
        <div class="public-service__layout">
            <article class="public-cp-article">
                <h1 class="public-cp-title public-page-title">{{ $page['name'] }}</h1>
                <p class="public-cp-meaning">{{ $page['lede'] }}</p>

                @foreach ($page['sections'] as $section)
                    <section class="public-content-section">
                        <h2>{{ $section['heading'] }}</h2>
                        @foreach ($section['paragraphs'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                        @if ($section['items'] !== [])
                            <ul class="public-bullet-list">
                                @foreach ($section['items'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach
            </article>

            <aside class="public-service__rail">
                <div class="public-service__promise">
                    <p class="public-page-eyebrow">At LugsNPlugs</p>
                    <p>{{ $page['promise'] }}</p>
                </div>

                @if ($page['related'] !== [])
                    <section class="public-service__related">
                        <h2>Related</h2>
                        <ul class="public-related-links">
                            @foreach ($page['related'] as $link)
                                <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <p class="public-service__rail-action">
                    <a class="public-cta public-cta--primary" href="{{ route('public.book', ['concern' => $page['concern']]) }}">Request an appointment</a>
                </p>
            </aside>
        </div>

        <section class="public-service__close">
            <h2 class="public-section-title">{{ $page['close_heading'] }}</h2>
            <p class="public-page-lede">{{ $page['close_lede'] }}</p>
            <p class="public-service__close-action">
                <a class="public-cta public-cta--primary" href="{{ route('public.book', ['concern' => $page['concern']]) }}">Request an appointment</a>
            </p>
        </section>
    </div>
</x-website.layout>
