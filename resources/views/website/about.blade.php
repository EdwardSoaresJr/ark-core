@php
    $photos = $website->storyPhotos();
@endphp

<x-website.layout :website="$website" :seo="$seo" page="about">
    <div class="public-home">
        <section class="public-home__split public-home__split--start">
            @if ($photos['team'] !== null)
                <figure class="public-home__figure">
                    <img src="{{ $photos['team']['url'] }}" alt="The LugsNPlugs Automotive team">
                    <figcaption class="public-home__caption">The LugsNPlugs Automotive team.</figcaption>
                </figure>
            @endif
            <div class="public-home__copy">
                <h1 class="public-page-title">A repair shop built around doing it right</h1>
                <p class="public-page-lede">LugsNPlugs Automotive is owned and operated by Edward and Molly Soares. What started with Edward working as a mobile mechanic grew into a Colorado Springs repair shop built around a simple idea: diagnose the vehicle first, explain what we found, and let the customer make an informed decision.</p>
                <p class="public-page-lede">We're not trying to move cars through as fast as possible. Every vehicle that comes through our shop belongs to somebody who depends on it.</p>
                <p class="public-home__personal">Every job is personal.</p>
                @if ($website->streetLine() !== '')
                    <p class="public-page-lede">{{ $website->streetLine() }}</p>
                @endif
                @if ($website->localityLine() !== '')
                    <p class="public-page-lede">{{ $website->localityLine() }}</p>
                @endif
                @if ($website->hoursLabel() !== '')
                    <p class="public-page-lede">{{ $website->hoursLabel() }}</p>
                @endif
                @if ($website->phone() !== '')
                    <p class="public-page-lede"><a class="public-link" href="tel:{{ preg_replace('/\D+/', '', $website->phone()) }}">{{ $website->phoneDisplay() }}</a></p>
                @endif
                <p class="mt-6">
                    <a class="public-cta public-cta--primary" href="{{ route('public.book') }}">Request an appointment</a>
                </p>
            </div>
        </section>
    </div>
</x-website.layout>
