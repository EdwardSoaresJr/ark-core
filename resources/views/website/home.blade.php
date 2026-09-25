@php
    $photos = $website->storyPhotos();
    $richard = $website->reviewByAttribution('Richard Conti');
    $greg = $website->reviewByAttribution('Greg Powell');
    $noise = [
        'brake-noise' => 'Brake noise',
        'wheel-bearing-noise' => 'Wheel bearing noise',
        'suspension-noise' => 'Suspension noise',
    ];
@endphp

<x-website.layout :website="$website" :seo="$seo" page="home">
    <div class="public-home">
        <section class="public-home__hero">
            <div class="public-home__copy">
                <p class="public-page-eyebrow">{{ $website->shopName() }}</p>
                <h1 class="public-page-title mt-2">Accurate Diagnostics. Honest Repairs.</h1>
                <p class="public-page-lede">We find the problem before we sell the repair.</p>
                <p class="public-page-lede">Warning light that keeps coming back? Strange noise nobody can pin down? Car running differently? Been given a big repair estimate and want a second opinion?</p>
                <p class="public-page-lede">Tell us what's happening. We'll start with the evidence and figure out what the vehicle actually needs.</p>
                <p class="mt-6 flex flex-wrap gap-3">
                    <a class="public-cta public-cta--primary" href="{{ route('public.book') }}">Request an appointment</a>
                    @if ($website->phone() !== '')
                        <a class="public-cta public-cta--secondary" href="tel:{{ preg_replace('/\D+/', '', $website->phone()) }}">Call {{ $website->phoneDisplay() }}</a>
                    @endif
                </p>
            </div>
            @if ($photos['bay'] !== null)
                <figure class="public-home__figure">
                    <img src="{{ $photos['bay']['url'] }}" alt="{{ $photos['bay']['alt'] }}">
                </figure>
            @endif
        </section>

        <section class="public-home__section">
            <div class="public-home__split">
                @if ($photos['team'] !== null)
                    <figure class="public-home__figure">
                        <img src="{{ $photos['team']['url'] }}" alt="The LugsNPlugs Automotive team">
                        <figcaption class="public-home__caption">The LugsNPlugs Automotive team.</figcaption>
                    </figure>
                @endif
                <div class="public-home__copy">
                    <h2 class="public-section-title">A repair shop built around doing it right</h2>
                    <p class="public-page-lede">LugsNPlugs Automotive is owned and operated by Edward and Molly Soares. What started with Edward working as a mobile mechanic grew into a Colorado Springs repair shop built around a simple idea: diagnose the vehicle first, explain what we found, and let the customer make an informed decision.</p>
                    <p class="public-page-lede">We're not trying to move cars through as fast as possible. Every vehicle that comes through our shop belongs to somebody who depends on it.</p>
                    <p class="public-page-lede">Every job is personal.</p>
                </div>
            </div>
        </section>

        <section class="public-home__section">
            <div class="public-home__copy">
                <h2 class="public-section-title">We test before we replace.</h2>
                <p class="public-page-lede">A warning light, a noise, or a low reading is a clue. It is not yet the repair.</p>
                <p class="public-page-lede">Before we recommend replacing parts, we test the vehicle against how that system is supposed to work. That can mean scan data, electrical testing, fuel trims, a pressure or vacuum test, and a mechanical inspection. The recommendation comes from what those tests show.</p>
                <p class="public-page-lede">After the repair, the same concern is checked again. The job is finished when the evidence says the problem is gone, not when the part is on the car.</p>
            </div>
            <div class="public-home__photos">
                @if ($photos['pressure'] !== null)
                    <figure class="public-home__figure">
                        <img src="{{ $photos['pressure']['url'] }}" alt="Pressure testing the cooling system before deciding what to replace.">
                        <figcaption class="public-home__caption">Pressure testing the cooling system before deciding what to replace.</figcaption>
                    </figure>
                @endif
                @if ($photos['findings'] !== null)
                    <figure class="public-home__figure">
                        <img src="{{ $photos['findings']['url'] }}" alt="Checking the findings before the recommendation.">
                        <figcaption class="public-home__caption">Checking the findings before the recommendation.</figcaption>
                    </figure>
                @endif
            </div>
        </section>

        <section class="public-home__section">
            <div class="public-home__copy">
                <h2 class="public-section-title">What's your car doing?</h2>
                <p class="public-page-lede">Pick the closest thing. Each page says what it can mean, whether you can keep driving, and how to request an appointment.</p>
            </div>
            <ul class="public-home__concerns">
                @if ($website->problem('check-engine-light') !== null)
                    <li><a class="public-link" href="{{ route('public.common-problems.show', 'check-engine-light') }}">Check engine light</a></li>
                @endif
                @if ($website->problem('car-wont-start') !== null)
                    <li><a class="public-link" href="{{ route('public.common-problems.show', 'car-wont-start') }}">Won't start</a></li>
                @endif
                @if ($website->problem('engine-overheating') !== null)
                    <li><a class="public-link" href="{{ route('public.common-problems.show', 'engine-overheating') }}">Overheating</a></li>
                @endif
                <li>
                    <details class="public-home__noise">
                        <summary>Strange noise</summary>
                        <ul>
                            @foreach ($noise as $slug => $label)
                                @if ($website->problem($slug) !== null)
                                    <li><a class="public-link" href="{{ route('public.common-problems.show', $slug) }}">{{ $label }}</a></li>
                                @endif
                            @endforeach
                        </ul>
                    </details>
                </li>
                @if ($website->problem('ac-not-cold') !== null)
                    <li><a class="public-link" href="{{ route('public.common-problems.show', 'ac-not-cold') }}">A/C not cold</a></li>
                @endif
                @if ($website->problem('electrical-diagnostics') !== null)
                    <li><a class="public-link" href="{{ route('public.common-problems.show', 'electrical-diagnostics') }}">Electrical problem</a></li>
                @endif
                @if ($website->problem('misfire-under-load') !== null)
                    <li><a class="public-link" href="{{ route('public.common-problems.show', 'misfire-under-load') }}">Running poorly</a></li>
                @endif
                <li><a class="public-link" href="{{ route('public.book', ['concern' => 'Something Else']) }}">Something else</a></li>
            </ul>
        </section>

        <section class="public-home__section">
            <div class="public-home__copy">
                <h2 class="public-section-title">What drivers say</h2>
                @if ($website->googleRating() !== '' && $website->googleReviewCount() > 0)
                    <p class="public-page-lede">
                        {{ $website->googleRating() }} from {{ $website->googleReviewCount() }} Google reviews.
                        @if ($website->googleReviewsUrl() !== '')
                            <a class="public-link" href="{{ $website->googleReviewsUrl() }}">Google reviews</a>
                        @endif
                    </p>
                @endif
                @foreach (array_filter([$richard, $greg]) as $review)
                    <blockquote class="public-home__quote">
                        <p>{{ $review['quote'] }}</p>
                        @if ($review['attribution'] !== '')
                            <footer>{{ $review['attribution'] }}</footer>
                        @endif
                    </blockquote>
                @endforeach

                <h2 class="public-section-title public-home__subhead">Warranty</h2>
                <p class="public-page-lede">Qualifying shop repairs are covered for 24 months or 24,000 miles on parts and labor, whichever comes first. <a class="public-link" href="{{ route('public.warranty') }}">Shop warranty</a></p>
                <p class="public-page-lede">RepairPal Certified coverage is separate: 12 months or 12,000 miles nationwide on qualifying repairs. <a class="public-link" href="{{ route('public.repairpal') }}">RepairPal</a></p>

                @if ($website->financingSummary() !== null)
                    <h2 class="public-section-title public-home__subhead">Financing</h2>
                    <p class="public-page-lede">{{ $website->financingSummary() }} <a class="public-link" href="{{ route('public.financing') }}">Financing</a></p>
                @endif
            </div>
        </section>

        <section class="public-home__section">
            <div class="public-home__copy">
                <h2 class="public-section-title">Tell us what's happening</h2>
                <p class="public-page-lede">An appointment request is how we start. It does not reserve a bay. Tell us the concern, the vehicle, and when you would like to come in. An advisor confirms the time during business hours.</p>
                @if ($website->hoursLabel() !== '')
                    <p class="public-page-lede">{{ $website->hoursLabel() }}</p>
                @endif
                @if ($website->streetLine() !== '')
                    <p class="public-page-lede">{{ $website->streetLine() }}</p>
                @endif
                @if ($website->localityLine() !== '')
                    <p class="public-page-lede">{{ $website->localityLine() }}</p>
                @endif
                <p class="mt-6 flex flex-wrap gap-3">
                    <a class="public-cta public-cta--primary" href="{{ route('public.book') }}">Request an appointment</a>
                    @if ($website->phone() !== '')
                        <a class="public-cta public-cta--secondary" href="tel:{{ preg_replace('/\D+/', '', $website->phone()) }}">Call {{ $website->phoneDisplay() }}</a>
                        <a class="public-cta public-cta--secondary" href="sms:{{ preg_replace('/\D+/', '', $website->phone()) }}">Text {{ $website->phoneDisplay() }}</a>
                    @endif
                </p>
            </div>
        </section>
    </div>
</x-website.layout>
