@php
    $shopName = $shop->displayName();
@endphp

<x-public.lead-intake :seo="$seo" publicSurfacePage="terms">
    <article class="public-static-page">
        <h1 class="public-page-title">Terms of use</h1>
        <p class="public-page-lede">
            By using the {{ $shopName }} website and My Account, you agree to these basic terms.
        </p>

        <section class="public-content-section">
            <h2>Website content</h2>
            <p>
                Repair guides and symptom pages describe common patterns we see in the shop. They are educational — not a diagnosis of your specific vehicle.
                Always have a qualified technician inspect your car before driving when safety is in question.
            </p>
        </section>

        <section class="public-content-section">
            <h2>Online requests</h2>
            <p>
                Submitting the contact form does not guarantee an appointment time. A service advisor will review your message and follow up during business hours.
            </p>
        </section>

        <section class="public-content-section">
            <h2>Changes</h2>
            <p>
                We may update this page as our online services evolve. Continued use of the site after changes means you accept the updated terms.
            </p>
        </section>

        @include('partials.public.financing-inline-panel')
    </article>
</x-public.lead-intake>
