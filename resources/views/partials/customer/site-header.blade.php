@php
    use App\Ark\Customer\CustomerSurfaceNavigation;
    use App\Ark\Operations\Settings\ShopSettings;

    $shop ??= ShopSettings::current();
    $shopName = $shop->displayName();
    $phoneDisplay = \App\Ark\Operations\PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
    $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';
    $smsHref = 'sms:'.$phoneTel;
    $cityState = trim(implode(', ', array_filter([$shop->city, $shop->state]))) ?: 'Demo City, ST';
    $addressParts = array_filter([
        $shop->publicationStreetAddress(),
        $cityState,
        trim((string) $shop->postal_code) !== '' ? $shop->postal_code : '80909',
    ]);
    $addressLine = implode(' · ', $addressParts);
    $navItems = app(CustomerSurfaceNavigation::class)->items();
    $homeUrl = \App\Ark\Customer\CustomerSurfaceUrls::publicHome();
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
        </div>
    </div>

    @if ($navItems !== [])
        <nav class="customer-header__nav customer-header__nav--desktop" aria-label="Customer">
            @foreach ($navItems as $item)
                <a
                    href="{{ $item['href'] }}"
                    @class([
                        'customer-header__nav-link',
                        'customer-header__nav-link--active' => $item['active'],
                    ])
                >
                    {{ $item['label'] }}
                </a>
            @endforeach

            @if (auth('portal')->check())
                <form method="POST" action="{{ route('portal.logout') }}" class="customer-header__sign-out">
                    @csrf
                    <button type="submit" class="customer-header__sign-out-btn">
                        Sign out
                    </button>
                </form>
            @endif
        </nav>
    @endif
</header>
