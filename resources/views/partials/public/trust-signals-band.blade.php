@php
    use App\Ark\Growth\PublicSurface\PublicMarketingUrl;

    $trustSignals = $trustSignals ?? app(\App\Ark\Operations\Leads\Public\PublicTrustSignalsProjection::class)->forDisplay();
    $modifier = $modifier ?? null;
@endphp

<ul @class([
    'public-trust-strip',
    'public-trust-strip--scroll' => $modifier === 'scroll',
])>
    @foreach ($trustSignals['header_pills'] as $pill)
        <li>
            @if (filled($pill['href'] ?? null))
                <a
                    href="{{ $pill['href'] }}"
                    @if (PublicMarketingUrl::opensInNewTab($pill['href'] ?? null))
                        target="_blank"
                        rel="noopener noreferrer"
                    @endif
                    @class(['public-trust-strip__link', 'public-trust-strip__link--external' => PublicMarketingUrl::opensInNewTab($pill['href'] ?? null)])
                >
                    {{ $pill['label'] }}
                </a>
            @else
                <span>{{ $pill['label'] }}</span>
            @endif
        </li>
    @endforeach
</ul>
