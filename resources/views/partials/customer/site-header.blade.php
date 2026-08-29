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
    $trackPublicSurfaceEvents = request()->routeIs('public.*');
    $isPublicSurface = request()->routeIs('public.*');
    $bookUrl = \Illuminate\Support\Facades\Route::has('public.book')
        ? route('public.book')
        : null;
    $showBookCta = $isPublicSurface && filled($bookUrl) && ! request()->routeIs('public.book');
    // Homepage trust lives in the photo hero — keep header compact on public.
    $showTrustStrip = request()->routeIs('portal.*');
@endphp

<header
    @class([
        'customer-header',
        'customer-header--public' => $isPublicSurface,
        'customer-header--sticky' => $isPublicSurface,
    ])
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

            {{-- Desktop: Call / Text stay visible. Mobile public: logo · theme · menu · Book (Call/Text in sticky + menu). --}}
            <a
                href="tel:{{ $phoneTel }}"
                @if ($trackPublicSurfaceEvents) data-public-surface-call @endif
                @class([
                    'customer-header__contact-link',
                    'customer-header__contact-link--primary',
                    'customer-header__contact-link--desktop-only' => $isPublicSurface,
                ])
            >
                <span class="customer-header__call-short">Call</span>
                <span class="customer-header__call-full">{{ $phoneDisplay }}</span>
            </a>
            <a
                href="{{ $smsHref }}"
                @if ($trackPublicSurfaceEvents) data-public-surface-text @endif
                @class([
                    'customer-header__contact-link',
                    'customer-header__contact-link--secondary',
                    'customer-header__contact-link--desktop-only' => $isPublicSurface,
                ])
            >
                Text
            </a>

            @if ($isPublicSurface && $navItems !== [])
                <button
                    type="button"
                    class="customer-header__menu-toggle md:hidden"
                    @click="menuOpen = !menuOpen"
                    :aria-expanded="menuOpen.toString()"
                    aria-controls="customer-header-mobile-nav"
                >
                    <span class="sr-only">Menu</span>
                    <svg x-show="!menuOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg x-cloak x-show="menuOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            @endif

            @if ($showBookCta)
                <a
                    href="{{ $bookUrl }}"
                    class="customer-header__book"
                >
                    <svg class="customer-header__book-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="16" rx="2"/>
                        <path d="M3 10h18M8 3v4M16 3v4"/>
                    </svg>
                    <span class="customer-header__book-label-full">Book an Appointment</span>
                    <span class="customer-header__book-label-short">Book</span>
                </a>
            @endif
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

        @if ($isPublicSurface)
            <nav
                id="customer-header-mobile-nav"
                class="customer-header__nav customer-header__nav--mobile md:hidden"
                aria-label="Customer mobile"
                x-cloak
                x-show="menuOpen"
                x-transition
            >
                @foreach ($navItems as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @click="menuOpen = false"
                        @class([
                            'customer-header__nav-link',
                            'customer-header__nav-link--active' => $item['active'],
                        ])
                    >
                        {{ $item['label'] }}
                    </a>
                @endforeach

                <a
                    href="{{ $smsHref }}"
                    @click="menuOpen = false"
                    @if ($trackPublicSurfaceEvents) data-public-surface-text @endif
                    class="customer-header__nav-link"
                >
                    Text the shop
                </a>

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
    @endif

    @if ($showTrustStrip)
        <div class="customer-header__trust">
            @include('partials.public.trust-signals-band')
        </div>
    @endif
</header>
