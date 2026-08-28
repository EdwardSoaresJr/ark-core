@php
    /** @var array{quote: string, attribution: string}|null $featuredCustomerReview */
    $featured = $featuredCustomerReview
        ?? \App\Ark\Operations\Leads\Public\PublicFeaturedReviewProjection::rotating($customerReviews ?? []);
@endphp

{{--
    Proof + financing share one chapter. Semantically distinct columns;
    one visual row on desktop, stacked on mobile.
--}}
<section class="public-home-proof-chapter" aria-label="Customer proof and financing">
    <div class="public-home-proof-chapter__inner customer-page-inset">
        @if ($featured)
            <div class="public-home-proof-chapter__proof public-surface--proof" aria-labelledby="public-testimonial-heading">
                <h2 id="public-testimonial-heading" class="public-home-proof-chapter__proof-title">What our customers say</h2>
                <p class="public-home-proof-chapter__stars" aria-label="5 star review">★★★★★</p>
                <blockquote class="public-home-proof-chapter__quote">
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
                        class="public-home-proof-chapter__more"
                    >
                        Read more reviews on Google
                    </a>
                @endif
            </div>
        @endif

        @include('partials.public.financing-inline-panel', [
            'trustSignals' => $trustSignals,
            'class' => 'public-home-proof-chapter__financing',
        ])
    </div>
</section>
