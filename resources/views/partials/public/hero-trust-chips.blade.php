@php
    use App\Ark\Growth\PublicSurface\PublicMarketingUrl;

    $heroTrustChips = $heroTrustChips
        ?? ($trustSignals['hero_chips'] ?? app(\App\Ark\Operations\Leads\Public\PublicTrustSignalsProjection::class)->forDisplay()['hero_chips']);
@endphp

@if ($heroTrustChips !== [])
    <ul class="public-hero-trust-chips {{ $class ?? '' }}" aria-label="Shop credentials">
        @foreach ($heroTrustChips as $chip)
            <li>
                @if (filled($chip['href'] ?? null))
                    <a
                        href="{{ $chip['href'] }}"
                        @if (PublicMarketingUrl::opensInNewTab($chip['href'] ?? null))
                            target="_blank"
                            rel="noopener noreferrer"
                        @endif
                        @class([
                            'public-hero-trust-chips__link',
                            'public-hero-trust-chips__link--external' => PublicMarketingUrl::opensInNewTab($chip['href'] ?? null),
                        ])
                    >
                        {{ $chip['label'] }}
                    </a>
                @else
                    <span>{{ $chip['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
