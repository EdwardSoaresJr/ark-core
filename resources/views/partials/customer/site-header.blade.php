@php
    use App\Ark\Customer\CustomerSurfaceNavigation;
    use App\Ark\Customer\CustomerSurfaceUrls;
    use App\Ark\Operations\Settings\ShopSettings;

    $shop ??= ShopSettings::current();
    $shopName = $shop->displayName();
    $phoneDisplay = \App\Ark\Operations\PhoneNumber::display($shop->phone);
    $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '';
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
    $allNavItems = app(CustomerSurfaceNavigation::class)->items();
    $bookItem = collect($allNavItems)->first(fn (array $item): bool => (bool) ($item['appointment'] ?? false));
    $navItems = array_values(array_filter($allNavItems, fn (array $item): bool => ! ($item['appointment'] ?? false)));
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

            @if ($phoneTel !== '')
                <a
                    href="tel:{{ $phoneTel }}"
                    class="customer-header__contact-link customer-header__contact-link--primary customer-header__contact-link--desktop-only"
                >
                    <span class="customer-header__call-short">Call</span>
                    <span class="customer-header__call-full">{{ $phoneDisplay }}</span>
                </a>
                <a
                    href="{{ $smsHref }}"
                    class="customer-header__contact-link customer-header__contact-link--secondary customer-header__contact-link--desktop-only"
                >
                    Text
                </a>
            @endif

            @if ($bookItem !== null)
                <a href="{{ $bookItem['href'] }}" class="customer-header__book" @if ($bookItem['active']) aria-current="page" @endif>
                    <span class="customer-header__book-label-short">Book</span>
                    <span class="customer-header__book-label-full">{{ $bookItem['label'] }}</span>
                </a>
            @endif

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
            @if ($phoneTel !== '')
                <a href="tel:{{ $phoneTel }}" class="customer-header__nav-link">Call {{ $phoneDisplay }}</a>
            @endif
        </nav>
    @endif
</header>
