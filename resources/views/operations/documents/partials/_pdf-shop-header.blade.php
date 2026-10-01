@php
    $shopName = $shop['name'] ?: config('app.name');
    $shopInitial = mb_strtoupper(mb_substr($shopName, 0, 1));
    $shopCityLine = trim(collect([$shop['city'] ?? null, $shop['state'] ?? null, $shop['postal_code'] ?? null])->filter()->implode(' '));
@endphp

<header class="header">
    <div class="document-heading">
        <div class="logo-mark">
            @if ($shop['logo_data_uri'] ?? null)
                <img src="{{ $shop['logo_data_uri'] }}" alt="{{ $shopName }} logo">
            @else
                {{ $shopInitial }} Auto
            @endif
        </div>
    </div>

    <div class="shop-contact">
        <h1>{{ $shopName }}</h1>
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
