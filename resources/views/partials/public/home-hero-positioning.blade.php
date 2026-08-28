@php
    /** @var list<array{quote: string, attribution: string}> $customerReviews */
    $heroHeadline = $headline ?? 'Need help with your vehicle?';
    $heroLede = $positioningLede ?? 'We find the real problem first. You get a clear estimate before we do any repairs.';
@endphp

<div class="public-hero">
    <h1 class="public-hero__title">{{ $heroHeadline }}</h1>

    @if (filled($heroLede))
        <p class="public-hero__lede">{{ $heroLede }}</p>
    @endif

    @include('partials.public.hero-trust-chips', [
        'class' => 'mt-4',
    ])

    @include('partials.public.google-review-featured', [
        'customerReviews' => $customerReviews,
        'reviewPageKey' => 'homepage',
        'class' => 'mt-6',
    ])
</div>
