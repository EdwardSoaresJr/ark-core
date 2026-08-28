@php
    $heroPhoto = $compositionPhotos['hero'] ?? null;
    $heroUrl = $heroPhoto['url'] ?? asset('shop-photos/shop-bay.webp');
    $bookUrl = route('public.book');
    $googleRating = (float) ($googleRating ?? 0);
    $googleReviewsUrl = $googleReviewsUrl ?? null;
@endphp

<section class="public-photo-hero" aria-labelledby="public-photo-hero-heading">
    <div class="public-photo-hero__media" aria-hidden="true">
        <img
            src="{{ $heroUrl }}"
            alt=""
            class="public-photo-hero__image"
            width="1600"
            height="900"
            fetchpriority="high"
        >
        <div class="public-photo-hero__shade"></div>
    </div>

    <div class="public-photo-hero__inner customer-page-inset">
        <div class="public-photo-hero__copy">
            <h1 id="public-photo-hero-heading" class="public-photo-hero__title">
                Accurate Diagnostics.<br>
                Honest Repairs.
            </h1>

            <p class="public-photo-hero__lede">
                We find the real problem first. You get a clear estimate before we do any repairs.
            </p>

            <div class="public-photo-hero__actions">
                <a
                    id="public-book-cta"
                    href="{{ $bookUrl }}"
                    class="public-cta public-cta--primary public-cta--xl"
                >
                    Book an Appointment
                </a>
            </div>
        </div>
    </div>

    <div class="public-photo-hero__trust">
        <div class="public-photo-hero__trust-inner customer-page-inset">
            <p class="public-photo-hero__facts" aria-label="Shop credentials">
                @if ($googleRating > 0 && filled($googleReviewsUrl))
                    <a
                        href="{{ $googleReviewsUrl }}"
                        @if (\App\Ark\Growth\PublicSurface\PublicMarketingUrl::opensInNewTab($googleReviewsUrl))
                            target="_blank"
                            rel="noopener noreferrer"
                        @endif
                        class="public-photo-hero__fact"
                    >{{ number_format($googleRating, 1) }} on Google</a>
                    <span class="public-photo-hero__fact-sep" aria-hidden="true">·</span>
                @endif
                <a href="{{ route('public.warranty') }}" class="public-photo-hero__fact">24/24 shop warranty</a>
                <span class="public-photo-hero__fact-sep" aria-hidden="true">·</span>
                <a href="{{ route('public.repairpal.certified') }}" class="public-photo-hero__fact">RepairPal Certified</a>
            </p>
        </div>
    </div>
</section>
