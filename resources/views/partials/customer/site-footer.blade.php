@php
    $footer = array_merge(
        \App\Ark\Customer\CustomerSurfaceFooterData::viewData(),
        $footer ?? [],
    );
@endphp

<footer class="customer-footer mt-auto" aria-label="Site footer">
    <div class="customer-footer__surface">
        <div class="customer-footer__inner customer-page-inset mx-auto w-full">
            <div class="customer-footer__grid">
                <div class="customer-footer__brand">
                    <p class="customer-footer__shop-name">{{ $footer['shop_name'] }}</p>
                    <div class="customer-footer__meta">
                        <p class="customer-footer__address">{{ $footer['address_line'] }}</p>
                        @if (filled($footer['business_hours_label']))
                            <p class="customer-footer__hours">{{ $footer['business_hours_label'] }}</p>
                        @endif
                    </div>
                    <div class="customer-footer__contact">
                        @if (filled($footer['phone_tel']))
                            <a href="tel:{{ $footer['phone_tel'] }}" class="customer-footer__contact-link">Call {{ $footer['phone_display'] }}</a>
                            <span class="customer-footer__contact-sep" aria-hidden="true">·</span>
                            <a href="sms:{{ $footer['phone_tel'] }}" class="customer-footer__contact-link">Text us</a>
                        @endif
                    </div>
                </div>

                @if (filled($footer['portal_url']))
                    <nav class="customer-footer__nav" aria-label="Footer">
                        <ul class="customer-footer__links">
                            <li><a href="{{ $footer['portal_url'] }}">{{ auth('portal')->check() ? 'My Account' : 'Sign In' }}</a></li>
                        </ul>
                    </nav>
                @endif
            </div>

            <div class="customer-footer__legal">
                <p class="customer-footer__copyright">
                    &copy; {{ date('Y') }} {{ $footer['shop_name'] }}.
                </p>
            </div>
        </div>
    </div>
</footer>
