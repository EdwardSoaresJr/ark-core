@props([
    'trustSignals' => null,
    'googleRating' => null,
    'googleReviewsUrl' => null,
])

@php
    use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
    use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
    use App\Ark\Operations\Leads\Public\PublicTrustSignalsProjection;

    $trustSignals = $trustSignals ?? app(PublicTrustSignalsProjection::class)->forDisplay();
    $googleRating = $googleRating ?? PublicSurfaceSettings::current()['google_rating'];
    $googleReviewsUrl = $googleReviewsUrl ?? PublicSurfaceSettings::current()['google_reviews_url'];
    $hasGoogleReviews = (float) $googleRating > 0 && filled($googleReviewsUrl);
@endphp

<div {{ $attributes->class(['public-trust-band']) }} aria-label="Shop credentials">
    <div class="public-trust-band__primary">
        @if ($hasGoogleReviews)
            <a href="{{ $googleReviewsUrl }}" target="_blank" rel="noopener noreferrer" class="public-trust-band__google" data-public-surface-reviews>
                <span class="public-trust-band__stars" aria-hidden="true">
                    <x-public.star-rating size="md" />
                </span>
                <span class="public-trust-band__google-copy">
                    <span class="public-trust-band__google-rating">{{ $googleRating }}★ Google rating</span>
                    <span class="public-trust-band__google-count">Colorado Springs drivers on Google</span>
                </span>
            </a>
        @endif
    </div>

    <ul class="public-trust-band__badges">
        @foreach ($trustSignals['proof_items'] as $item)
            @if (str_contains(strtolower($item['label']), 'google'))
                @continue
            @endif
            <li class="public-trust-band__badge">
                @if (filled($item['href'] ?? null))
                    <a
                        href="{{ $item['href'] }}"
                        @if (PublicMarketingUrl::opensInNewTab($item['href'] ?? null))
                            target="_blank"
                            rel="noopener noreferrer"
                        @endif
                        @class(['public-trust-band__badge-link', 'public-trust-band__badge-link--external' => PublicMarketingUrl::opensInNewTab($item['href'] ?? null)])
                    >
                        <span class="public-trust-band__badge-label">{{ $item['label'] }}</span>
                    </a>
                @else
                    <span class="public-trust-band__badge-label">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
