@php
    $shopName = $shop->displayName();
@endphp

<x-public.lead-intake :seo="$seo" publicSurfacePage="warranty" :editorial-sections="true">
    <article class="public-static-page">
        <h1 class="public-page-title">Repair warranty</h1>
        <p class="public-page-lede">
            Qualifying repairs at {{ $shopName }} are covered by our shop warranty for 24 months / 24,000 miles on parts and labor, where applicable — whichever comes first.
            RepairPal Certified warranty coverage is separate: 12 months / 12,000 miles nationwide on qualifying repairs.
        </p>

        <section class="public-content-section">
            <h2>What it covers</h2>
            <p>
                When we do a covered repair, the parts and our workmanship are covered for 24 months or 24,000 miles — whichever comes first.
                If something related to that repair fails within the warranty period, bring the vehicle back and we’ll look at it with you.
            </p>
        </section>

        <section class="public-content-section">
            <h2>How to use it</h2>
            <p>
                Keep your invoice. If something related to the repair goes wrong, call or text the shop and tell us what changed.
                An advisor will help you schedule a follow-up look.
            </p>
        </section>

        <section class="public-content-section">
            <h2>Questions</h2>
            <p>
                Coverage can depend on the type of repair and the parts used. Your advisor confirms what’s covered on your estimate before work starts.
                RepairPal Certified warranty is 12 months / 12,000 miles. For that nationwide program, see our
                <a href="{{ route('public.repairpal.warranty') }}" class="public-link">RepairPal warranty page</a>.
            </p>
        </section>

        @include('partials.public.financing-inline-panel')
    </article>
</x-public.lead-intake>
