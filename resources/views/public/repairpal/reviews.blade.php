@php
    $shopName = $shop->displayName();
@endphp

<x-public.lead-intake :seo="$seo" publicSurfacePage="repairpal-reviews" :editorial-sections="true">
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <h1 class="public-page-title">RepairPal reviews</h1>
            <p class="public-page-lede">
                Independent review sites help you check a shop without relying only on the shop’s own website.
                RepairPal reviews sit alongside our Google reviews — they do not replace them.
            </p>

            @include('partials.public.repairpal-authority-nav', ['current' => 'reviews'])

            <section class="public-content-section">
                <h2>How RepairPal collects reviews</h2>
                <p>
                    RepairPal invites customers who use its marketplace and related services to leave feedback on the shop listing.
                    Those reviews live on RepairPal’s platform, under RepairPal’s terms — not as copy we rewrite for marketing pages.
                </p>
            </section>

            <section class="public-content-section">
                <h2>Why independent reviews matter</h2>
                <p>
                    A shop can choose what to put on its homepage. An independent platform cannot be fully controlled by the shop.
                    When you read RepairPal reviews on RepairPal’s site, you are reading the platform’s record — the same way Google reviews live on Google.
                    That separation is useful: a third-party record for drivers who want it.
                </p>
            </section>

            <section class="public-content-section">
                <h2>Google reviews still matter</h2>
                <p>
                    Most Demo City drivers find us through Google Maps and Search. We show our Google rating on the homepage because it is a primary local signal.
                    RepairPal is a second signal for drivers who already trust that network — or who arrive from a RepairPal estimate.
                </p>
                @if (filled($googleReviewsUrl ?? null))
                    <p class="mt-3">
                        <a
                            href="{{ $googleReviewsUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="public-link text-sm"
                        >
                            See our Google reviews →
                        </a>
                    </p>
                @endif
            </section>

            <section class="public-content-section">
                <h2>Read RepairPal reviews on RepairPal</h2>
                <p>
                    We do not republish RepairPal review text on this site. The live profile is the accurate, up-to-date source — including ratings, recent feedback, and certification status.
                </p>
                @include('partials.public.repairpal-profile-cta', ['repairPalUrl' => $repairPalUrl])
            </section>

            <section class="public-content-section">
                <h2>Related</h2>
                <ul>
                    <li><a href="{{ route('public.repairpal.certified') }}" class="public-link">What RepairPal Certified means</a></li>
                    <li><a href="{{ route('public.repairpal.warranty') }}" class="public-link">Nationwide warranty on qualifying work</a></li>
                </ul>
            </section>
        </x-slot:primary>

        <x-slot:rail>
            @include('partials.public.lead-form', [
                'shop' => $shop,
                'phoneVerificationRequired' => $phoneVerificationRequired,
                'formRenderedAt' => $formRenderedAt,
                'responseTimeHint' => $responseTimeHint,
                'formHeading' => 'Ready to request service?',
                'formSubheading' => 'Tell us about the vehicle — we’ll follow up during business hours.',
                'submitLabel' => \App\Ark\Operations\Leads\Public\PublicLeadFormCopy::SUBMIT_LABEL,
                'useShell' => false,
            ])
        </x-slot:rail>
    </x-customer.split-page>
</x-public.lead-intake>
