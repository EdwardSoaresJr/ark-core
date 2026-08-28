{{-- Public homepage body — shared by / and /book underlay --}}
@include('partials.public.home-photo-hero', [
    'compositionPhotos' => $compositionPhotos,
    'googleRating' => $googleRating,
    'googleReviewsUrl' => $googleReviewsUrl,
    'trustSignals' => $trustSignals,
])

@include('partials.public.home-second-opinion', [
    'compositionPhotos' => $compositionPhotos,
])

@include('partials.public.home-book-appointment', [
    'compositionPhotos' => $compositionPhotos,
])

<section class="public-home-symptoms">
    <div class="public-home-symptoms__inner customer-page-inset">
        @include('partials.public.home-common-problems', [
            'featuredCommonProblems' => $featuredCommonProblems,
        ])
    </div>
</section>

@include('partials.public.home-proof-chapter', [
    'featuredCustomerReview' => $featuredCustomerReview,
    'customerReviews' => $customerReviews,
    'googleRating' => $googleRating,
    'googleReviewsUrl' => $googleReviewsUrl,
    'trustSignals' => $trustSignals,
])
