<x-website.layout :website="$website" :seo="$seo" page="services">
    <div class="public-home">
        <article class="public-home__about">
            <h1 class="public-page-title">Services</h1>
            <div class="public-home__copy">
                <p class="public-page-lede">LugsNPlugs diagnoses and repairs the systems that keep a car safe and reliable. If you already know the symptom or the code, start with <a class="public-link" href="{{ route('public.common-problems.index') }}">Problems</a>.</p>
            </div>

            @foreach ($services as $service)
                <section class="public-home__section" id="{{ $service['id'] }}">
                    <div class="public-home__copy">
                        <h2 class="public-section-title">{{ $service['name'] }}</h2>
                        <p class="public-page-lede">{{ $service['summary'] }}</p>
                        @if ($service['links'] !== [])
                            <ul class="public-services__links">
                                @foreach ($service['links'] as $link)
                                    <li><a class="public-link" href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            @endforeach

            <p class="mt-6">
                <a class="public-cta public-cta--primary" href="{{ route('public.book') }}">Request an appointment</a>
            </p>
        </article>
    </div>
</x-website.layout>
