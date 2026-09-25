@php
    use App\Ark\Customer\CustomerSurfaceNavigation;
    use App\Ark\Customer\CustomerSurfaceUrls;
    use App\Ark\Operations\Settings\ShopSettings;

    $shop ??= ShopSettings::current();
    $shopName = $shop->displayName();
    $phoneDisplay = \App\Ark\Operations\PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
    $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';
    $smsHref = 'sms:'.$phoneTel;
    $cityState = trim(implode(', ', array_filter([
        trim((string) $shop->city),
        trim((string) $shop->state),
    ])));
    $street = trim((string) $shop->address_line_1) !== ''
        ? $shop->googleMatchedStreetAddress()
        : '';
    $addressParts = array_filter([
        $street,
        $cityState,
        trim((string) $shop->postal_code),
    ]);
    $addressLine = implode(' · ', $addressParts);
    $navItems = app(CustomerSurfaceNavigation::class)->items();
    $homeUrl = CustomerSurfaceUrls::shopHome();
@endphp

<header
    class="customer-header"
    x-data="{ menuOpen: false }"
    @keydown.escape.window="menuOpen = false"
>
    <div class="customer-header__bar">
        <a href="{{ $homeUrl }}" class="customer-header__brand">
            @if (filled($logoUrl ?? null))
                <img src="{{ $logoUrl }}" alt="{{ $shopName }}" class="customer-header__logo">
            @endif
            <span class="customer-header__identity">
                <span class="customer-header__name">{{ $shopName }}</span>
                <span class="customer-header__meta">{{ $addressLine }}</span>
            </span>
        </a>

        <div class="customer-header__contact">
            @include('partials.customer.theme-toggle')

            <a
                href="tel:{{ $phoneTel }}"
                class="customer-header__contact-link customer-header__contact-link--primary"
            >
                <span class="customer-header__call-short">Call</span>
                <span class="customer-header__call-full">{{ $phoneDisplay }}</span>
            </a>
            <a
                href="{{ $smsHref }}"
                class="customer-header__contact-link customer-header__contact-link--secondary"
            >
                Text
            </a>

            @if ($navItems !== [])
                <button
                    type="button"
                    class="customer-header__menu-toggle"
                    @click="menuOpen = ! menuOpen"
                    :aria-expanded="menuOpen.toString()"
                    aria-controls="customer-nav-mobile"
                    aria-label="Menu"
                >
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" d="M3 5h14M3 10h14M3 15h14" />
                    </svg>
                </button>
            @endif
        </div>
    </div>

    @if ($navItems !== [])
        <nav class="customer-header__nav customer-header__nav--desktop" aria-label="Customer">
            @include('partials.customer.site-nav-links', ['navItems' => $navItems])
        </nav>
        <nav
            id="customer-nav-mobile"
            class="customer-header__nav customer-header__nav--mobile"
            aria-label="Customer"
            x-show="menuOpen"
            x-cloak
            @click="menuOpen = false"
        >
            @include('partials.customer.site-nav-links', ['navItems' => $navItems])
        </nav>
    @endif
</header>
