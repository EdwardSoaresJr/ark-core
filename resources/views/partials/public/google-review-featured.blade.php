@php
    /** @var list<array{quote: string, attribution: string}> $customerReviews */
    use App\Ark\Operations\Leads\Public\PublicFeaturedReviewProjection;

    $featuredReview = PublicFeaturedReviewProjection::forPage(
        $reviewPageKey ?? null,
        $customerReviews,
    );
@endphp

@if ($featuredReview !== null)
    <figure @class(['public-review-quote', $class ?? 'mt-6'])>
        <div class="flex flex-wrap items-center gap-2">
            <x-public.star-rating size="md" />
            <span class="text-sm font-semibold text-slate-800">Google review</span>
        </div>
        <blockquote class="mt-2.5 text-base leading-relaxed text-slate-700">
            <p>&ldquo;{{ $featuredReview['quote'] }}&rdquo;</p>
            @if (filled($featuredReview['attribution'] ?? null))
                <footer class="mt-2 text-sm font-medium text-slate-500">— {{ $featuredReview['attribution'] }}</footer>
            @endif
        </blockquote>
    </figure>
@endif
