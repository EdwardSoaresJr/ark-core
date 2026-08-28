@php
    /** @var array{quote: string, attribution: string}|null $featuredCustomerReview */
    $featured = $featuredCustomerReview
        ?? \App\Ark\Operations\Leads\Public\PublicFeaturedReviewProjection::rotating($customerReviews ?? []);
@endphp

@if ($featured)
    <section class="public-testimonial-band public-surface--proof" aria-labelledby="public-testimonial-heading">
        <div class="public-testimonial-band__inner customer-page-inset">
            <h2 id="public-testimonial-heading" class="public-testimonial-band__title">What our customers say</h2>
            <p class="public-testimonial-band__stars" aria-label="5 star review">★★★★★</p>
            <blockquote class="public-testimonial-band__quote">
                <p>“{{ $featured['quote'] }}”</p>
                <footer>— {{ $featured['attribution'] }}</footer>
            </blockquote>

            @if ((float) ($googleRating ?? 0) > 0 && filled($googleReviewsUrl ?? null))
                <a
                    href="{{ $googleReviewsUrl }}"
                    @if (\App\Ark\Growth\PublicSurface\PublicMarketingUrl::opensInNewTab($googleReviewsUrl))
                        target="_blank"
                        rel="noopener noreferrer"
                    @endif
                    class="public-testimonial-band__more"
                >
                    Read more reviews on Google
                </a>
            @endif
        </div>
    </section>
@endif
