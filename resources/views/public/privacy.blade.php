@php
    $shopName = $shop->displayName();
@endphp

<x-public.lead-intake :seo="$seo" publicSurfacePage="privacy">
    <article class="public-static-page">
        <h1 class="public-page-title">Privacy policy</h1>
        <p class="public-page-lede">
            {{ $shopName }} respects your privacy. This page explains what we collect through the website and My Account, and how we use it.
        </p>

        <section class="public-content-section">
            <h2>Information we collect</h2>
            <p>
                When you contact the shop, sign in to My Account, or approve work online, we may collect your name, phone number, email address, vehicle information, and messages you send us.
            </p>
        </section>

        <section class="public-content-section">
            <h2>How we use it</h2>
            <p>
                We use this information to respond to your request, schedule service, perform repairs, send estimates and invoices, and communicate about your vehicle.
                We do not sell your personal information.
            </p>
        </section>

        <section class="public-content-section">
            <h2>Contact</h2>
            <p>
                Questions about this policy? Call or text the shop using the number on our website, or send a message through the homepage form.
            </p>
        </section>

        @include('partials.public.financing-inline-panel')
    </article>
</x-public.lead-intake>
