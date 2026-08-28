@php
    $shopName = $shop->displayName();
@endphp

<x-public.lead-intake :seo="$seo" publicSurfacePage="repairpal" :editorial-sections="true">
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <h1 class="public-page-title">RepairPal at {{ $shopName }}</h1>
            <p class="public-page-lede">
                {{ $shopName }} is a RepairPal Certified auto repair shop in Colorado Springs.
                These pages explain what that certification means — before you leave our site to check it on RepairPal.
            </p>

            @include('partials.public.repairpal-authority-nav', ['current' => 'hub'])

            <section class="public-content-section">
                <h2>Start here</h2>
                <ul class="mt-3 space-y-3">
                    <li>
                        <a href="{{ route('public.repairpal.certified') }}" class="public-link">
                            RepairPal Certified
                        </a>
                        <p class="mt-0.5 text-sm text-slate-600">What certification means, why we earned it, and what customers gain.</p>
                    </li>
                    <li>
                        <a href="{{ route('public.repairpal.reviews') }}" class="public-link">
                            RepairPal Reviews
                        </a>
                        <p class="mt-0.5 text-sm text-slate-600">Why independent reviews matter — alongside our Google reviews.</p>
                    </li>
                    <li>
                        <a href="{{ route('public.repairpal.warranty') }}" class="public-link">
                            RepairPal Warranty
                        </a>
                        <p class="mt-0.5 text-sm text-slate-600">Nationwide 12 month / 12,000 mile RepairPal Certified coverage on qualifying repairs.</p>
                    </li>
                </ul>
            </section>

            <section class="public-content-section">
                <h2>Check it yourself</h2>
                <p>
                    Want to confirm the listing yourself? Our official RepairPal profile is the source of record for certification status and RepairPal reviews.
                </p>
                @include('partials.public.repairpal-profile-cta', ['repairPalUrl' => $repairPalUrl])
            </section>
        </x-slot:primary>

        <x-slot:rail>
            @include('partials.public.lead-form', [
                'shop' => $shop,
                'phoneVerificationRequired' => $phoneVerificationRequired,
                'formRenderedAt' => $formRenderedAt,
                'responseTimeHint' => $responseTimeHint,
                'formHeading' => 'Talk to a service advisor',
                'formSubheading' => 'Tell us about the vehicle — we’ll follow up during business hours.',
                'submitLabel' => \App\Ark\Operations\Leads\Public\PublicLeadFormCopy::SUBMIT_LABEL,
                'useShell' => false,
            ])
        </x-slot:rail>
    </x-customer.split-page>
</x-public.lead-intake>
