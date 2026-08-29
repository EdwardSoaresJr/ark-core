@php
    $shopName = $shop->displayName();
@endphp

<x-public.lead-intake :seo="$seo" publicSurfacePage="repairpal-certified" :editorial-sections="true">
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <h1 class="public-page-title">RepairPal Certified</h1>
            <p class="public-page-lede">
                {{ $shopName }} is a RepairPal Certified shop. RepairPal is an independent network.
                They review shops for workmanship standards, fair pricing practices, and customer experience — not a badge we designed for ourselves.
            </p>

            @include('partials.public.repairpal-authority-nav', ['current' => 'certified'])

            <section class="public-content-section">
                <h2>What RepairPal is</h2>
                <p>
                    RepairPal is an independent auto repair marketplace and certification network.
                    Drivers use it to find shops that meet published quality and pricing standards, compare estimates, and read reviews collected outside any one shop’s website.
                </p>
            </section>

            <section class="public-content-section">
                <h2>What “RepairPal Certified” means</h2>
                <p>
                    Certification is not a paid marketing sticker. RepairPal evaluates shops against published criteria that include trained technicians, parts practices, pricing transparency, and customer service.
                    When you see RepairPal Certified on our site, you can check the same status on RepairPal’s public listing for {{ $shopName }}.
                </p>
            </section>

            <section class="public-content-section">
                <h2>Why {{ $shopName }} chose certification</h2>
                <p>
                    We already find the problem before we recommend a repair. Certification lets you verify that with a third party.
                    If you arrive from RepairPal — or you recognize the badge from elsewhere — you should be able to confirm we meet the same bar we claim on our own pages.
                </p>
            </section>

            <section class="public-content-section">
                <h2>What customers gain</h2>
                <ul>
                    <li>A shop credential you can verify independently — not only our word.</li>
                    <li>Access to RepairPal’s review and estimate tools when you use their platform.</li>
                    <li>Nationwide RepairPal Certified warranty (12 months / 12,000 miles) on qualifying repairs done at this shop — see our <a href="{{ route('public.repairpal.warranty') }}" class="public-link">RepairPal warranty page</a>.</li>
                    <li>The same diagnostic standard we use for every Demo City customer: verify the problem before recommending the repair.</li>
                </ul>
            </section>

            <section class="public-content-section">
                <h2>How we diagnose</h2>
                <p>
                    Codes and check-engine lights are clues — not a parts list. We use diagnostic tools and live data from the car to confirm the fault, then explain what is wrong, what can wait, and what should be repaired now.
                    That matches what we say on our homepage and Common Problems pages. RepairPal Certification sits beside that promise; it does not replace it.
                </p>
            </section>

            <section class="public-content-section">
                <h2>Common questions</h2>
                <dl class="space-y-4">
                    <div>
                        <dt class="font-semibold text-slate-900">Is RepairPal the same as Google reviews?</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-slate-600 sm:text-base">
                            No. Google reviews stay on Google. RepairPal collects its own reviews through its platform.
                            We keep both signals because different customers use different sites — see <a href="{{ route('public.repairpal.reviews') }}" class="public-link">RepairPal reviews</a>.
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Do I have to book through RepairPal?</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-slate-600 sm:text-base">
                            No. You can request service on this site, call or text the shop, or use RepairPal if you prefer their estimate flow. The work is done at {{ $shopName }} either way.
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">How do I verify you are still certified?</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-slate-600 sm:text-base">
                            Open our official RepairPal profile. That listing is the third-party source of record for certification status.
                        </dd>
                    </div>
                </dl>
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
