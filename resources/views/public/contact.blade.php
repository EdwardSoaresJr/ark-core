@php
    /** @var array<string, mixed> $contact */
    use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
@endphp

<x-public.lead-intake :seo="$seo" publicSurfacePage="contact" :editorial-sections="true">
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <p class="public-page-eyebrow">Contact us</p>
            <h1 class="public-page-title mt-2">Contact {{ $contact['shop_name'] }}</h1>
            <p class="public-page-lede">
                Questions for the shop? Call, text, or send a message — we’ll get back to you.
            </p>

            <div class="public-contact-book-band">
                <p class="public-contact-book-band__title">Need help with your vehicle?</p>
                <p class="public-contact-book-band__lede">
                    For service or diagnostics, book a time. We’ll confirm it with you.
                </p>
                <a href="{{ $contact['book_href'] }}" class="public-cta public-cta--primary">
                    Book an Appointment
                </a>
            </div>

            <div class="public-contact-actions">
                <a
                    href="tel:{{ $contact['phone_tel'] }}"
                    data-public-surface-call
                    class="public-cta public-cta--secondary"
                >
                    Call {{ $contact['phone_display'] }}
                </a>
                <a
                    href="{{ $contact['sms_href'] }}"
                    data-public-surface-text
                    class="public-cta public-cta--ghost"
                >
                    Text us
                </a>
                <a href="#send-a-message" class="public-cta public-cta--ghost">
                    Send a message
                </a>
                <a
                    href="{{ $contact['google_maps_url'] }}"
                    @if (PublicMarketingUrl::opensInNewTab($contact['google_maps_url']))
                        target="_blank"
                        rel="noopener noreferrer"
                    @endif
                    class="public-cta public-cta--ghost"
                >
                    Get directions
                </a>
            </div>

            <section class="public-content-section mt-8 space-y-5" aria-labelledby="contact-shop-info-heading">
                <h2 id="contact-shop-info-heading">Shop information</h2>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Address</p>
                    <p class="mt-1 whitespace-pre-line text-base font-semibold text-slate-950">{{ $contact['address_multiline'] }}</p>
                </div>

                @if (filled($contact['business_hours_label']))
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Business hours</p>
                        <p class="mt-1 text-base font-semibold text-slate-950">{{ $contact['business_hours_label'] }}</p>
                    </div>
                @endif

                @if (filled($contact['visit_notes']))
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Parking &amp; drop-off</p>
                        <p class="mt-1 text-sm leading-6 text-slate-700">{{ $contact['visit_notes'] }}</p>
                    </div>
                @endif

                <div class="public-contact-page__map">
                    <iframe
                        title="Map to {{ $contact['shop_name'] }}"
                        src="{{ $contact['google_maps_embed_url'] }}"
                        class="h-64 w-full border-0 sm:h-72"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                        allowfullscreen
                    ></iframe>
                </div>
            </section>

            <section class="public-content-section" aria-labelledby="contact-reach-heading">
                <h2 id="contact-reach-heading">Ways to reach us</h2>
                <ul class="public-reach-list">
                    @foreach ($contact['reach_methods'] as $method)
                        <li>
                            <a
                                href="{{ $method['href'] }}"
                                @if (! empty($method['external']) && PublicMarketingUrl::opensInNewTab($method['href']))
                                    target="_blank"
                                    rel="noopener noreferrer"
                                @endif
                                @if ($method['key'] === 'call') data-public-surface-call @endif
                                @if ($method['key'] === 'text') data-public-surface-text @endif
                                class="public-reach-list__link"
                            >
                                <span class="public-reach-list__label">{{ $method['label'] }}</span>
                                <span class="public-reach-list__desc">{{ $method['description'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if (($contact['faqs'] ?? []) !== [])
                <section class="public-content-section" aria-labelledby="contact-faq-heading">
                    <h2 id="contact-faq-heading">Frequently asked questions</h2>
                    <div class="mt-2">
                        @foreach ($contact['faqs'] as $index => $faq)
                            <details class="public-contact-page__faq group">
                                <summary class="cursor-pointer list-none text-sm font-semibold text-slate-950 marker:content-none">
                                    <span class="flex items-start justify-between gap-3">
                                        <span>{{ $faq['question'] }}</span>
                                        <span class="mt-0.5 text-slate-400 transition group-open:rotate-45" aria-hidden="true">+</span>
                                    </span>
                                </summary>
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $faq['answer'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif
        </x-slot:primary>

        <x-slot:rail>
            <div class="scroll-mt-24 space-y-4" id="tell-the-shop">
                @include('partials.public.contact-inquiry-form', [
                    'shop' => $shop,
                    'formRenderedAt' => $formRenderedAt,
                    'formHeading' => 'Send a message',
                    'formSubheading' => 'Billing, hours, or anything that isn’t a service visit.',
                    'submitLabel' => 'Send message',
                    'surfaceContext' => \App\Ark\Operations\Leads\Public\PublicSurfaceContext::contact(),
                ])

                <div class="public-panel">
                    <p class="text-sm font-semibold text-slate-900">What happens next</p>
                    <ol class="mt-3 space-y-2.5 text-sm leading-relaxed text-slate-600">
                        <li class="flex gap-2.5">
                            <span class="public-step-marker">1</span>
                            We review your message
                        </li>
                        <li class="flex gap-2.5">
                            <span class="public-step-marker">2</span>
                            Someone from the shop gets back to you
                        </li>
                        <li class="flex gap-2.5">
                            <span class="public-step-marker">3</span>
                            If you need service, we’ll help you book a time
                        </li>
                    </ol>
                </div>
            </div>
        </x-slot:rail>
    </x-customer.split-page>
</x-public.lead-intake>
