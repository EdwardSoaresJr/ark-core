@php
    use App\Ark\Growth\PublicSurface\PublicMarketingUrl;

    $footer = array_merge(
        \App\Ark\Customer\CustomerSurfaceFooterData::viewData(),
        $footer ?? [],
    );
    $hasTrustColumn = ($footer['trust_points'] ?? []) !== [];
@endphp

<footer class="customer-footer mt-auto" aria-label="Site footer">
    <div class="customer-footer__surface">
        <div class="customer-footer__inner customer-page-inset mx-auto w-full">
            @if (($footer['variant'] ?? 'compact') === 'trust')
                <div @class([
                    'customer-footer__columns grid gap-6 md:items-start md:gap-8',
                    'md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]' => ! $hasTrustColumn,
                    'md:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)_minmax(0,1.2fr)]' => $hasTrustColumn,
                ])>
                    <section class="customer-footer__column" aria-labelledby="footer-contact-heading">
                        <p id="footer-contact-heading" class="customer-footer__shop-name">{{ $footer['shop_name'] }}</p>
                        <p class="customer-footer__identity">
                            {{ $footer['service_tagline'] ?? 'Diagnostics first. Then the repair.' }}
                            <span class="customer-footer__identity-sep" aria-hidden="true">·</span>
                            {{ $footer['city_state'] }}
                        </p>

                        <p class="customer-footer__street">{{ $footer['street_address'] }}</p>

                        @if (filled($footer['business_hours_label']))
                            <p class="customer-footer__hours">{{ $footer['business_hours_label'] }}</p>
                        @endif

                        <div class="customer-footer__actions">
                            <a href="tel:{{ $footer['phone_tel'] }}" class="customer-footer__action-link">Call {{ $footer['phone_display'] }}</a>
                            <a href="sms:{{ $footer['phone_tel'] }}" class="customer-footer__action-link">Text us</a>
                            <a
                                href="{{ $footer['google_maps_url'] }}"
                                @if (PublicMarketingUrl::opensInNewTab($footer['google_maps_url'] ?? null))
                                    target="_blank"
                                    rel="noopener noreferrer"
                                @endif
                                class="customer-footer__action-link"
                            >
                                Directions
                            </a>
                        </div>

                        @if (($footer['social_compact_links'] ?? []) !== [])
                            <x-customer.connect-with-us
                                class="mt-4"
                                variant="compact"
                                headline="Follow us"
                                :links="$footer['social_compact_links']"
                            />
                        @endif
                    </section>

                    <nav class="customer-footer__column" aria-labelledby="footer-nav-heading">
                        <p id="footer-nav-heading" class="customer-footer__column-title mb-2.5 text-[0.6875rem] font-bold uppercase tracking-wider text-slate-400">Helpful links</p>
                        <ul class="customer-footer__links m-0 flex list-none flex-col gap-2.5 p-0">
                            @foreach ($footer['nav_links'] ?? [] as $link)
                                <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>

                    @if ($hasTrustColumn)
                        <section class="customer-footer__column" aria-labelledby="footer-trust-heading">
                            <p id="footer-trust-heading" class="customer-footer__column-title mb-2.5 text-[0.6875rem] font-bold uppercase tracking-wider text-slate-400">Why customers choose us</p>
                            <ul class="customer-footer__trust-list m-0 flex list-none flex-col gap-2 p-0">
                                @foreach ($footer['trust_points'] as $point)
                                    <li class="relative pl-4 text-sm leading-relaxed text-slate-600 before:absolute before:left-0 before:font-bold before:text-[#0099cc] before:content-['✓']">{{ $point }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            @else
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
                            <a href="tel:{{ $footer['phone_tel'] }}" class="customer-footer__contact-link">Call {{ $footer['phone_display'] }}</a>
                            <span class="customer-footer__contact-sep" aria-hidden="true">·</span>
                            <a href="sms:{{ $footer['phone_tel'] }}" class="customer-footer__contact-link">Text us</a>
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
            @endif

            <div class="customer-footer__legal">
                <p class="customer-footer__copyright">
                    &copy; {{ date('Y') }} {{ $footer['shop_name'] }}. Colorado Springs independent repair.
                </p>
                @if (filled($footer['privacy_url']) || filled($footer['terms_url']))
                    <div class="customer-footer__legal-links">
                        @if (filled($footer['privacy_url']))
                            <a href="{{ $footer['privacy_url'] }}">Privacy</a>
                        @endif
                        @if (filled($footer['privacy_url']) && filled($footer['terms_url']))
                            <span class="customer-footer__legal-sep" aria-hidden="true">·</span>
                        @endif
                        @if (filled($footer['terms_url']))
                            <a href="{{ $footer['terms_url'] }}">Terms</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</footer>
