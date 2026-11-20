@php
    $shopName = $shop['name'] ?: config('app.name');
    $hasLogo = filled($shop['logo_data_uri'] ?? null);
    $shopCityLine = trim(collect([$shop['city'] ?? null, $shop['state'] ?? null, $shop['postal_code'] ?? null])->filter()->implode(' '));
@endphp

<header class="header">
    <div class="document-heading">
        @if ($hasLogo)
            <div class="logo-mark">
                <img src="{{ $shop['logo_data_uri'] }}" alt="{{ $shopName }} logo">
            </div>
        @else
            <div class="logo-mark logo-mark--name" data-shop-brand-name>
                <span class="brand-name">{{ $shopName }}</span>
            </div>
        @endif
    </div>

    <div class="shop-contact">
        @if ($hasLogo)
            <h1 class="shop-name">{{ $shopName }}</h1>
        @endif
        <p class="muted small contact-line">
            @if ($shop['phone'] ?? null)
                {{ $shop['phone'] }}
            @endif
            @if ($shop['email'] ?? null)
                {{ ($shop['phone'] ?? null) ? ' | ' : '' }}{{ $shop['email'] }}
            @endif
        </p>
        <p class="muted small">
            @if ($shop['address_line_1'] ?? null)
                {{ $shop['address_line_1'] }}
            @endif
            @if ($shop['address_line_2'] ?? null)
                {{ ($shop['address_line_1'] ?? null) ? ', ' : '' }}{{ $shop['address_line_2'] }}
            @endif
        </p>
        @if ($shopCityLine)
            <p class="muted small">{{ $shopCityLine }}</p>
        @endif
    </div>
</header>
