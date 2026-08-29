@php
    $shop = $shop ?? \App\Ark\Operations\Settings\ShopSettings::current();
    $shopName = $shop->displayName();
    $phoneDisplay = \App\Ark\Operations\PhoneNumber::display($shop->phone) ?: '(719) 413-6227';
    $phoneTel = preg_replace('/\D+/', '', (string) $shop->phone) ?: '7194136227';
    $addressParts = array_filter([
        $shop->publicationStreetAddress(),
        trim(implode(', ', array_filter([$shop->city, $shop->state]))),
    ]);
    $addressLine = $addressParts !== [] ? implode(' · ', $addressParts) : 'Demo City, ST';
    $hours = app(\App\Ark\Operations\Leads\Public\PublicSurfaceSettings::class)::current()['business_hours_label'] ?? null;
@endphp

<section class="public-page-section public-page-section--accent" aria-label="Contact the shop">
    <div class="public-page-section__inner customer-page-inset">
        <div class="public-cta-band">
            <div class="public-cta-band__copy">
                <h2 class="public-cta-band__title">Questions about your vehicle?</h2>
                <p class="public-cta-band__lede">Call, text, or send us what&apos;s happening — we&apos;ll help you figure out the next step.</p>
                @if (filled($hours))
                    <p class="public-cta-band__hours">{{ $hours }}</p>
                @endif
                <p class="public-cta-band__location">{{ $shopName }} · {{ $addressLine }}</p>
            </div>

            <div class="public-cta-band__actions">
                <a href="tel:{{ $phoneTel }}" data-public-surface-call class="public-cta-band__btn public-cta-band__btn--primary">
                    Call {{ $phoneDisplay }}
                </a>
                <a href="sms:{{ $phoneTel }}" data-public-surface-text class="public-cta-band__btn public-cta-band__btn--secondary">
                    Text us
                </a>
                <a href="{{ route('public.home') }}#tell-the-shop" class="public-cta-band__btn public-cta-band__btn--secondary">
                    Send a message
                </a>
            </div>
        </div>
    </div>
</section>
