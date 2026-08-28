@props([
    'links' => null,
    'variant' => 'panel',
    'headline' => 'Stay connected',
    'lede' => 'Follow the shop for recent repairs, updates, and helpful automotive tips.',
    'headingId' => null,
])

@php
    use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
    use App\Ark\Operations\Leads\Public\ShopSocialProfiles;

    $links = is_array($links) ? $links : ShopSocialProfiles::forDisplay();
    $headingId = filled($headingId) ? (string) $headingId : 'customer-connect-'.uniqid();
@endphp

@if ($links !== [])
    @if ($variant === 'compact')
        <nav {{ $attributes->class(['customer-connect customer-connect--compact']) }} aria-label="{{ $headline }}">
            <p class="customer-connect__compact-label">{{ $headline }}</p>
            <ul class="customer-connect__compact-list">
                @foreach ($links as $link)
                    <li>
                        <a
                            href="{{ $link['href'] }}"
                            @if (PublicMarketingUrl::opensInNewTab($link['href']))
                                target="_blank"
                                rel="noopener noreferrer"
                            @endif
                            class="customer-connect__compact-link"
                        >{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @else
        <section {{ $attributes->class(['customer-connect customer-connect--panel']) }} aria-labelledby="{{ $headingId }}">
            <div class="customer-connect__intro">
                <h2 id="{{ $headingId }}" class="customer-connect__headline">{{ $headline }}</h2>
                @if (filled($lede))
                    <p class="customer-connect__lede">{{ $lede }}</p>
                @endif
            </div>

            <ul class="customer-connect__grid">
                @foreach ($links as $link)
                    <li>
                        <a
                            href="{{ $link['href'] }}"
                            @if (PublicMarketingUrl::opensInNewTab($link['href']))
                                target="_blank"
                                rel="noopener noreferrer"
                            @endif
                            class="customer-connect__card customer-connect__card--{{ $link['brand'] }}"
                        >
                            <span class="customer-connect__icon" aria-hidden="true">
                                @include('partials.customer.social-brand-icon', ['brand' => $link['brand']])
                            </span>
                            <span class="customer-connect__copy">
                                <span class="customer-connect__label">{{ $link['label'] }}</span>
                                <span class="customer-connect__description">{{ $link['description'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endif
