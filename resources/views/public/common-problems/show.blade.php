@php
    /** @var array $problem */
    /** @var array $authority */
    $shopName = $shop->displayName();
    $dtcCode = $authority['dtc_code'] ?? null;
    $plainMeaning = $authority['plain_english_meaning'] ?? null;
@endphp

<x-public.lead-intake :seo="$seo" :publicSurfacePage="'common-problems.'.$problem['slug']" :indexable="! ($staffPreview ?? false)">
    <x-customer.split-page variant="public">
        <x-slot:primary>
            @if ($staffPreview ?? false)
                <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-semibold">Staff preview</p>
                    <p class="mt-1 text-amber-900">
                        Draft only — not live on the public site until published.
                        @if (! empty($previewPublicPath))
                            Public path after publish: <span class="font-mono">{{ $previewPublicPath }}</span>
                        @endif
                    </p>
                </div>
            @endif

            <article class="public-cp-article">
                <header class="public-cp-identity">
                    @if (filled($dtcCode))
                        <p class="public-cp-dtc">
                            <span class="public-cp-dtc__code">{{ $dtcCode }}</span>
                        </p>
                    @endif

                    <h1 class="public-page-title public-cp-title">{{ $authority['title'] }}</h1>

                    @if (filled($plainMeaning))
                        <p class="public-cp-meaning">{{ $plainMeaning }}</p>
                    @endif
                </header>

                {{-- Answer-first: useful meaning immediately under identity. No trust/promo between H1 and answer. --}}
                <div class="public-cp-answer">
                    <p>{{ $authority['problem'] }}</p>
                </div>

                @include('partials.public.common-problem-featured-media', [
                    'featuredMedia' => $authority['featured_media'],
                    'featuredMediaGallery' => $authority['featured_media_gallery'],
                    'class' => 'public-cp-media mt-6',
                ])

                @include('partials.public.common-problem-authority', [
                    'authority' => $authority,
                    'shopName' => $shopName,
                ])

                <p class="public-cp-quiet-proof">
                    @if ((float) ($googleRating ?? 0) > 0 && filled($googleReviewsUrl ?? null))
                        <a href="{{ $googleReviewsUrl }}" target="_blank" rel="noopener noreferrer" class="public-link">{{ number_format((float) $googleRating, 1) }} on Google</a>
                        <span aria-hidden="true"> · </span>
                    @endif
                    <a href="{{ route('public.warranty') }}" class="public-link">24/24 shop warranty</a>
                    <span aria-hidden="true"> · </span>
                    <a href="{{ route('public.repairpal.certified') }}" class="public-link">RepairPal Certified</a>
                </p>

                @include('partials.public.financing-inline-panel', [
                    'class' => 'public-cp-financing mt-8',
                ])

                <p class="public-cp-footer-note">
                    Colorado Springs independent repair. We test before we recommend parts.
                    <a href="{{ route('public.common-problems.index') }}" class="public-link">Browse all common problems</a>
                </p>
            </article>
        </x-slot:primary>

        <x-slot:rail>
            @include('partials.public.common-problem-book-cta', [
                ...$leadForm,
                'shop' => $shop,
            ])
        </x-slot:rail>
    </x-customer.split-page>
</x-public.lead-intake>
