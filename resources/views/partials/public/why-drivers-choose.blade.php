@php
    use App\Ark\Growth\PublicSurface\PublicMarketingUrl;

    $trustSignals = $trustSignals ?? app(\App\Ark\Operations\Leads\Public\PublicTrustSignalsProjection::class)->forDisplay();
@endphp

<section @class(['public-proof-section', $class ?? 'mt-6']) aria-labelledby="why-drivers-heading">
    <h2 id="why-drivers-heading" class="public-section-title">
        {{ $trustSignals['section_title'] }}
    </h2>

    <ul class="public-proof-list mt-3">
        @foreach ($trustSignals['proof_items'] as $item)
            <li class="public-proof-list__item">
                <div class="min-w-0">
                    @if (filled($item['href'] ?? null))
                        <a
                            href="{{ $item['href'] }}"
                            @if (PublicMarketingUrl::opensInNewTab($item['href'] ?? null))
                                target="_blank"
                                rel="noopener noreferrer"
                            @endif
                            class="public-proof-list__label public-proof-list__label--link"
                        >
                            {{ $item['label'] }}
                        </a>
                    @else
                        <p class="public-proof-list__label">{{ $item['label'] }}</p>
                    @endif
                    @if (filled($item['detail'] ?? null))
                        <p class="public-proof-list__detail">{{ $item['detail'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
