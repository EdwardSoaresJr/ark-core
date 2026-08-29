@php
    $shopName = $shop->displayName();
@endphp

<x-public.lead-intake :seo="$seo" publicSurfacePage="repairpal-warranty" :editorial-sections="true">
    <x-customer.split-page variant="public">
        <x-slot:primary>
            <h1 class="public-page-title">RepairPal nationwide warranty</h1>
            <p class="public-page-lede">
                The RepairPal Certified warranty is 12 months / 12,000 miles on qualifying parts and labor — whichever comes first.
                That coverage is nationwide through the RepairPal Certified warranty program.
                {{ $shopName }} also offers a separate shop warranty of 24 months / 24,000 miles on qualifying parts and labor, where applicable.
            </p>

            @include('partials.public.repairpal-authority-nav', ['current' => 'warranty'])

            <section class="public-content-section">
                <h2>What is covered</h2>
                <p>
                    Under the RepairPal Certified warranty, qualifying parts and labor are covered for 12 months or 12,000 miles — whichever comes first.
                    Your advisor confirms which items on your estimate qualify before work starts. Coverage can depend on the type of repair and the parts used.
                </p>
            </section>

            <section class="public-content-section">
                <h2>Nationwide coverage</h2>
                <p>
                    This warranty is meant for travel — not only for drivers who stay in Demo City.
                    If a related issue shows up while you are away, participating RepairPal Certified shops can help look at warranty work under the program’s terms.
                </p>
            </section>

            <section class="public-content-section">
                <h2>If you need warranty help while traveling</h2>
                <ol>
                    <li>Keep your invoice and repair paperwork from {{ $shopName }}.</li>
                    <li>Contact us first when you can — call or text the shop and tell us what changed.</li>
                    <li>If you need local help on the road, ask for a RepairPal Certified shop in that area and share your invoice so they can coordinate coverage under the program.</li>
                </ol>
                <p class="mt-3">
                    Exact claim steps depend on the repair and the shop helping you. We’ll walk you through what to do when you reach out.
                </p>
            </section>

            <section class="public-content-section">
                <h2>Why this matters on the road</h2>
                <p>
                    A covered problem should not leave you stuck far from home with no path forward.
                    Nationwide warranty coverage on qualifying work is one reason certification matters — it can extend help beyond our building.
                </p>
            </section>

            <section class="public-content-section">
                <h2>Common warranty questions</h2>
                <dl class="space-y-4">
                    <div>
                        <dt class="font-semibold text-slate-900">Is every repair covered?</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-slate-600 sm:text-base">
                            No. Qualifying repairs are confirmed on your estimate. Ask your advisor if you are unsure before authorizing work.
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">How is this different from the shop warranty page?</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-slate-600 sm:text-base">
                            Our <a href="{{ route('public.warranty') }}" class="public-link">repair warranty</a> page describes the Demo Auto Repair shop warranty: 24 months / 24,000 miles on qualifying parts and labor, where applicable.
                            This page is the RepairPal Certified warranty: 12 months / 12,000 miles nationwide on qualifying repairs.
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-900">Where can I verify the program?</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-slate-600 sm:text-base">
                            Certification status and program details are published on our official RepairPal profile.
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
                'formHeading' => 'Questions about warranty coverage?',
                'formSubheading' => 'Tell us about the vehicle and repair — we’ll help clarify next steps.',
                'submitLabel' => \App\Ark\Operations\Leads\Public\PublicLeadFormCopy::SUBMIT_LABEL,
                'useShell' => false,
            ])
        </x-slot:rail>
    </x-customer.split-page>
</x-public.lead-intake>
